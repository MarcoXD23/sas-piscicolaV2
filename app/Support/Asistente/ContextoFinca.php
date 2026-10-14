<?php

namespace App\Support\Asistente;

use App\Models\DespachoAlimento;
use App\Models\Especie;
use App\Models\EventoAgenda;
use App\Models\HoraExtra;
use App\Models\Lago;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Ronda;
use App\Models\Siembra;
use App\Models\Tarea;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use App\Support\CalculadoraRacion;
use App\Support\Campo\PaqueteTrabajo;
use App\Support\CoberturaBodega;
use App\Support\Formato;
use App\Support\Indicadores;
use App\Support\LotesPorEspecie;
use App\Support\Menu;
use App\Support\Region;

/**
 * Arma lo que el asistente sabe de la finca. Solo incluye lo que el rol de quien pregunta puede ver en la aplicación:
 * el asistente nunca debe ser una puerta trasera a información restringida (bodega, ventas, nómina, personal).
 */
class ContextoFinca
{
    /** Temas ocultos de los que la persona ve su propia parte, y la pantalla del menú donde la ve. */
    private const PARTES_PROPIAS = [
        'nomina' => 'mi-trabajo.index',   // sus propios pagos de nómina
        'dinero' => 'mi-trabajo.index',   // su propio saldo de pescado
    ];

    /**
     * Instrucciones de la finca activa para quien pregunta (por defecto, la persona con sesión). No cambian entre sus
     * preguntas (prefijo cacheable): solo con el catálogo de la finca, su país o los roles de la persona.
     * Las fichas son las de las especies de ESTA finca; lo que falta va como «sin dato» para que no se invente.
     */
    public static function instrucciones(?User $usuario = null): string
    {
        $usuario ??= auth()->user();
        $especies = Especie::query()->activas()->orderBy('nombre')->get()->map(fn (Especie $especie) => self::ficha($especie->ficha()))->implode("\n");
        $pais = Region::pais();

        // Dos avisos distintos: dato de la finca ausente y dato oculto para el rol
        return __('asistente.instrucciones', [
            'dato_ausente' => __('asistente.avisos.dato_ausente'),
            'sin_acceso_rol' => __('asistente.avisos.sin_acceso_rol'),
            // Sin país configurado no se asume ninguno
            'pais' => $pais ? __('asistente.pais', ['pais' => Region::nombrePais($pais)]) : __('asistente.sin_pais'),
            'guia' => $usuario ? "\n".self::guia($usuario)."\n" : '',
            'especies' => $especies,
        ]);
    }

    /**
     * Guía de la app: una línea por cada entrada del menú que ve la persona (App\Support\Menu), con su nombre real y lo
     * que su rol hace ahí. Nunca nombra una pantalla que la persona no tiene.
     */
    public static function guia(User $usuario): string
    {
        $usuario->loadMissing('roles');
        $roles = $usuario->roles->pluck('nombre');
        // Las claves son nombres de ruta (con puntos): se lee el arreglo completo, no con notación de puntos
        $pantallas = __('asistente.guia.pantallas');

        $lineas = Menu::para($usuario)->map(function (array $enlace) use ($usuario, $roles, $pantallas) {
            if ($enlace['ruta'] === 'campo') {
                return "- {$enlace['texto']}: ".self::registroRapido($usuario);
            }
            $texto = $pantallas[$enlace['ruta']];
            // Un texto por rol: los de los roles de la persona, en el orden de la guía
            if (is_array($texto)) {
                $texto = collect($texto)->filter(fn (string $linea, string $rol) => $roles->contains($rol))->unique()->implode(' ');
            }

            return "- {$enlace['texto']}: {$texto}";
        });

        return __('asistente.guia.titulo')."\n".$lineas->implode("\n");
    }

    /** Lo que la persona puede registrar en Registro rápido (las mismas secciones del paquete de trabajo). */
    private static function registroRapido(User $usuario): string
    {
        $acciones = collect(PaqueteTrabajo::secciones($usuario))->map(fn (string $seccion) => __('asistente.guia.acciones.'.$seccion));
        $texto = __('asistente.guia.registro_rapido', ['acciones' => $acciones->join(', ', ' y ')]);

        return $usuario->tieneRol(...Turno::ROLES) ? $texto.' '.__('asistente.guia.en_turno') : $texto;
    }

    /** Una línea por especie con los datos de su ficha; cada dato ausente se escribe como «sin dato». */
    private static function ficha(array $ficha): string
    {
        $sinDato = __('asistente.sin_dato');
        $rango = fn (?array $r, string $clave) => $r ? __('asistente.'.$clave, ['min' => Formato::sinCeros($r[0]), 'max' => Formato::sinCeros($r[1])]) : $sinDato;

        return __('asistente.ficha', [
            'nombre' => $ficha['nombre'],
            'cientifico' => $ficha['cientifico'] ?: $sinDato,
            'clima' => __($ficha['clima'] === Especie::FRIO ? 'especies.agua_fria' : 'especies.agua_calida'),
            'temperatura' => $rango($ficha['temperatura'], 'rango_temperatura'),
            'oxigeno' => $ficha['oxigeno_min'] !== null ? __('asistente.valor_oxigeno', ['valor' => Formato::sinCeros($ficha['oxigeno_min'])]) : $sinDato,
            'ph' => $rango($ficha['ph'], 'rango_ph'),
            'meses' => $ficha['meses_cosecha'] ?? $sinDato,
            'peso' => $ficha['peso_comercial'] ?? $sinDato,
            'policultivo' => $ficha['policultivo'] ?? $sinDato,
            'color' => $ficha['color'] ?? $sinDato,
            'aspecto' => $ficha['aspecto'] ?? $sinDato,
            'cultivo' => self::partesFicha($ficha['cultivo'], 'campos_cultivo', $sinDato),
            'procesamiento' => self::partesFicha($ficha['procesamiento'], 'campos_procesamiento', $sinDato),
        ]);
    }

    /** «Preparación del estanque: …; Siembra: sin dato; …» */
    private static function partesFicha(array $partes, string $etiquetas, string $sinDato): string
    {
        return collect($partes)->map(fn (?string $texto, string $campo) => __('especies.ficha.'.$etiquetas.'.'.$campo).': '.($texto ?? $sinDato))->implode('; ');
    }

    /**
     * Temas que existen en la finca pero el rol de quien pregunta no puede ver. Se nombran en el contexto para que el
     * asistente responda «no tengo esa información para tu rol» solo en esos casos, y «no tengo ese dato» en los demás.
     *
     * @return list<string> claves de asistente.temas
     */
    public static function temasOcultos(User $usuario): array
    {
        $esPropietario = $usuario->tieneRol(Rol::PROPIETARIO);
        $esTecnico = $usuario->tieneRol(Rol::PROPIETARIO, Rol::ADMINISTRADOR);
        $veLagos = $usuario->tieneRol(Rol::PROPIETARIO, Rol::ADMINISTRADOR, Rol::TRABAJADOR);
        $esCelador = $usuario->tieneRol(Rol::CELADOR);

        return array_keys(array_filter([
            'produccion' => ! $esTecnico,
            'bodega' => ! $esTecnico,
            'dinero' => ! $esTecnico,
            'raciones' => ! $veLagos,
            'seguridad' => ! ($esTecnico || $esCelador),
            'personal' => ! $esPropietario,
            'nomina' => ! $esPropietario,
            'agenda' => ! $esPropietario,
        ]));
    }

    /**
     * Un tema oculto por línea. Si la persona ve su propia parte del tema en una pantalla de su menú (PARTES_PROPIAS),
     * la línea la dice con el nombre real de esa pantalla: lo arma el código, el modelo solo lo repite.
     *
     * @param  list<string>  $ocultos
     */
    private static function lineasOcultas(User $usuario, array $ocultos): string
    {
        $menu = Menu::para($usuario)->keyBy('ruta');

        return collect($ocultos)->map(function (string $tema) use ($menu) {
            $ruta = self::PARTES_PROPIAS[$tema] ?? null;

            return $ruta !== null && $menu->has($ruta)
                ? __('asistente.tema_oculto_con_parte', [
                    'tema' => __('asistente.temas.'.$tema),
                    'parte' => __('asistente.partes_propias.'.$tema, ['pantalla' => $menu[$ruta]['texto']]),
                ])
                : __('asistente.tema_oculto', ['tema' => __('asistente.temas.'.$tema)]);
        })->implode("\n");
    }

    /** Estado actual de la finca, filtrado por el rol. */
    public static function para(User $usuario): string
    {
        $usuario->loadMissing('roles');
        $roles = $usuario->roles->pluck('nombre');
        $esPropietario = $usuario->tieneRol(Rol::PROPIETARIO);
        $esTecnico = $usuario->tieneRol(Rol::PROPIETARIO, Rol::ADMINISTRADOR);
        $veLagos = $usuario->tieneRol(Rol::PROPIETARIO, Rol::ADMINISTRADOR, Rol::TRABAJADOR);
        $esCelador = $usuario->tieneRol(Rol::CELADOR);

        // Sin el nombre de la persona ni el de la finca: al asistente le basta el rol
        $secciones = [
            'Ahora: '.Region::ahora()->translatedFormat('l j \d\e F \d\e Y, H:i').'.',
            "Rol de quien pregunta: {$roles->join(' y ')}.",
        ];

        if ($veLagos || $esCelador) {
            $secciones[] = self::lagos($veLagos);
        }
        if ($usuario->tieneRol(...Turno::ROLES)) {
            $secciones[] = self::turnoYTareas($usuario);
        }
        if ($esTecnico) {
            $mortalidad = Indicadores::mortalidadReciente();
            $produccion = Indicadores::produccionActual();
            $secciones[] = 'Producción hoy: '.Formato::numero($produccion['biomasa_kg'], 1).' kg de biomasa, '
                .Formato::numero($produccion['peces_vivos']).' peces vivos en '.$produccion['lagos_activos'].' lagos. '
                ."Mortalidad de los últimos 30 días: {$mortalidad['muertos']} peces ({$mortalidad['porcentaje']} %).";
        }
        if ($esTecnico || $esCelador) {
            $secciones[] = self::novedadesNoche();
        }
        // Bodega y ventas: el propietario las consulta y el administrador las opera
        if ($esTecnico) {
            $secciones[] = self::bodegaYVentas();
        }
        if ($esPropietario) {
            $secciones[] = self::soloPropietario();
        }

        $ocultos = self::temasOcultos($usuario);
        if ($ocultos !== []) {
            $secciones[] = __('asistente.ocultos_por_rol')."\n".self::lineasOcultas($usuario, $ocultos);
        }

        return "DATOS ACTUALES DE LA FINCA\n\n".implode("\n\n", array_filter($secciones));
    }

    private static function lagos(bool $conProduccion): string
    {
        $lagos = Lago::query()->with(['siembrasActivas.lagoOrigen', 'ultimaMedicion', 'aireacionActiva', 'tratamientosEnRetiro'])->orderBy('nombre')->get();
        if ($lagos->isEmpty()) {
            return 'Lagos: no hay lagos registrados.';
        }

        $lineas = $lagos->map(function (Lago $lago) use ($conProduccion) {
            $partes = ["Lago {$lago->nombre} ({$lago->tipo})"];

            if ($lago->siembrasActivas->isEmpty()) {
                $partes[] = 'sin peces';
            } elseif ($conProduccion) {
                // Por especie; con varios lotes de la misma especie, el total y cada lote con su origen
                $lote = fn (Siembra $siembra) => Formato::numero($siembra->peces_vivos).' peces de '
                    .Formato::numero($siembra->peso_actual_g, 1).' g, '.Formato::numero($siembra->biomasa_kg, 1)." kg, {$siembra->dias_cultivo} días";
                foreach (LotesPorEspecie::agrupar($lago->siembrasActivas) as $grupo) {
                    if ($grupo['lotes'] === 1) {
                        $partes[] = "{$grupo['especie']}: ".$lote($grupo['siembras']->first());

                        continue;
                    }
                    $detalle = $grupo['siembras']->values()->map(fn (Siembra $siembra, int $indice) => 'lote '.($indice + 1).' ('.mb_lcfirst($siembra->textoOrigen()).'): '.$lote($siembra));
                    $partes[] = "{$grupo['especie']}: {$grupo['lotes']} lotes, en total ".Formato::numero($grupo['peces']).' peces, peso promedio '
                        .LotesPorEspecie::textoPeso($grupo).', '.Formato::numero($grupo['biomasa_kg'], 1).' kg ('.$detalle->implode('; ').')';
                }
                $racion = CalculadoraRacion::paraLago($lago);
                $partes[] = 'ración sugerida '.Formato::numero($racion['total_kg_dia'], 1)." kg/día en {$racion['tomas']} tomas"
                    .($racion['factor'] < 1 ? ' (reducida por la calidad del agua)' : '');
            } else {
                $partes[] = 'especies: '.LotesPorEspecie::nombres($lago->siembrasActivas);
            }

            if ($medicion = $lago->ultimaMedicion) {
                $valores = array_filter([
                    $medicion->oxigeno_mg_l !== null ? 'oxígeno '.Formato::numero($medicion->oxigeno_mg_l, 2).' mg/L' : null,
                    $medicion->temperatura_c !== null ? Formato::numero($medicion->temperatura_c, 1).' °C' : null,
                    $medicion->ph !== null ? 'pH '.Formato::numero($medicion->ph, 2) : null,
                ]);
                $partes[] = 'última medición '.$medicion->medido_at->formatoHora('d/m H:i').': '.implode(', ', $valores);
                foreach ($medicion->alertas($lago->siembrasActivas->pluck('especie')) as $alerta) {
                    $partes[] = "ALERTA: {$alerta}";
                }
            }
            $partes[] = $lago->aireacionActiva ? 'aireador encendido desde las '.$lago->aireacionActiva->encendido_at->formatoHora('H:i') : 'aireador apagado';
            foreach ($lago->tratamientosEnRetiro as $tratamiento) {
                $partes[] = "en retiro sanitario hasta el {$tratamiento->finRetiro()->format('d/m/Y')} (no cosechar)";
            }

            return '- '.implode('; ', $partes);
        });

        return "Lagos:\n".$lineas->implode("\n");
    }

    private static function turnoYTareas(User $usuario): string
    {
        $turno = $usuario->turnos()->enCurso()->first();
        $proximo = $usuario->proximoTurno();
        $tareas = Tarea::query()->with('lago')->where('asignada_a', $usuario->id)->pendientes()->orderBy('fecha')->limit(10)->get();

        $texto = $turno
            ? "Está en turno de {$turno->rol} hasta las {$turno->termina_at->formatoHora('H:i')}."
            : 'Está fuera de turno.'.($proximo ? " Próximo turno: {$proximo->inicia_at->translatedFormat('l j, H:i')} ({$proximo->rol})." : '');

        $texto .= $tareas->isEmpty()
            ? ' No tiene tareas pendientes.'
            : "\nSus tareas pendientes:\n".$tareas->map(fn (Tarea $t) => "- {$t->titulo} para el {$t->fecha->format('d/m')}"
                .($t->lago ? " (lago {$t->lago->nombre})" : '').($t->estaVencida() ? ' VENCIDA' : ''))->implode("\n");

        return $texto;
    }

    private static function novedadesNoche(): string
    {
        $novedades = Ronda::novedades()->with('lago')->where('registrada_at', '>=', now()->subDay())->latest('registrada_at')->limit(10)->get();

        return $novedades->isEmpty()
            ? 'Seguridad: sin novedades en las últimas 24 horas.'
            : "Novedades de seguridad (24 horas):\n".$novedades->map(fn (Ronda $r) => "- {$r->registrada_at->formatoHora('d/m H:i')} {$r->textoTipo()}"
                .($r->lago ? " en {$r->lago->nombre}" : '').": {$r->descripcion}")->implode("\n");
    }

    /** Bodega, dinero, personal y agenda: exclusivos del propietario. */
    private static function bodegaYVentas(): string
    {
        $bodega = CoberturaBodega::calcular()->map(fn ($item) => "- {$item['stock']->tipo_concentrado}: "
            .Formato::numero($item['stock']->cantidad_kg, 1).' kg'.($item['dias'] !== null ? ', alcanza unos '.Formato::numero($item['dias'], 1).' días' : '')
            .($item['estado'] !== CoberturaBodega::SUFICIENTE ? " ({$item['estado']})" : ''))->implode("\n");

        $sinRecibir = DespachoAlimento::query()->whereDoesntHave('recepciones')->orderBy('fecha')->get()
            ->map(fn (DespachoAlimento $d) => "- {$d->tipo_concentrado}: ".Formato::numero($d->cantidad_kg, 1)." kg despachados el {$d->fecha->format('d/m')} ({$d->proveedor})")->implode("\n");

        return implode("\n\n", array_filter([
            'Bodega de alimento (lo recibido menos las raciones):'."\n".($bodega ?: '- vacía'),
            $sinRecibir ? "Despachos de concentrado todavía sin recibir:\n{$sinRecibir}" : null,
            'Ventas del mes: '.Formato::dinero((int) Venta::where('fecha', '>=', Region::hoy()->startOfMonth()->toDateString())->sum('total'))
                .'. Por cobrar: '.Formato::dinero((int) Venta::conSaldo()->sum('saldo')).'.',
        ]));
    }

    private static function soloPropietario(): string
    {
        $recordatorios = EventoAgenda::query()->paraRecordar()->orderBy('inicia_at')->limit(10)->get()
            ->map(fn (EventoAgenda $e) => "- {$e->titulo} ({$e->textoTipo()}) el {$e->inicia_at->format('d/m')}".($e->estaVencido() ? ' VENCIDO' : ''))->implode("\n");

        return implode("\n\n", array_filter([
            'Solicitudes de personal por revisar: '.Permiso::query()->pendientes()->count().' permisos y '
                .HoraExtra::query()->pendientes()->count().' reportes de horas extra.',
            $recordatorios ? "Agenda pendiente:\n{$recordatorios}" : null,
        ]));
    }
}
