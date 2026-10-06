<?php

namespace App\Support\Asistente;

use App\Models\EventoAgenda;
use App\Models\HoraExtra;
use App\Models\Lago;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Ronda;
use App\Models\Tarea;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use App\Support\CalculadoraRacion;
use App\Support\CatalogoEspecies;
use App\Support\CoberturaBodega;
use App\Support\Formato;
use App\Support\Indicadores;
use App\Support\Region;

/**
 * Arma lo que el asistente sabe de la finca. Solo incluye lo que el rol de quien pregunta puede ver en la aplicación:
 * el asistente nunca debe ser una puerta trasera a información restringida (bodega, ventas, nómina, personal).
 */
class ContextoFinca
{
    /** Instrucciones fijas: no cambian entre preguntas para que el prefijo sea cacheable. */
    public static function instrucciones(): string
    {
        $especies = collect(CatalogoEspecies::todas())->map(fn ($ficha) => sprintf(
            '- %s (%s): agua %s, %d a %d °C, oxígeno mínimo %s mg/L, pH %s a %s, cosecha en %s con %s. Policultivo: %s',
            $ficha['nombre'], $ficha['cientifico'], $ficha['clima'], $ficha['temperatura'][0], $ficha['temperatura'][1],
            $ficha['oxigeno_min'], $ficha['ph'][0], $ficha['ph'][1], $ficha['meses_cosecha'], $ficha['peso_comercial'], $ficha['policultivo'],
        ))->implode("\n");

        return <<<TEXTO
        Eres el asistente de El SAS Piscícola, una aplicación para la gestión de fincas piscícolas.
        Te escribe una persona de la finca desde el celular, muchas veces mientras trabaja.

        Cómo responder:
        - En español sencillo, claro y breve. Ve al grano; si piden un cálculo, muestra el resultado primero.
        - Usa solo los datos de la finca que vienen después de estas instrucciones. Si un dato no está, dilo; no lo inventes.
        - No asumas el país de la finca ni leyes, normas o trámites que no aparezcan en los datos. Si preguntan por normas
          o trámites, di que no tienes esa información y sugiere consultar a la autoridad sanitaria o agropecuaria de su país.
        - Esos datos solo incluyen lo que esta persona puede ver según su rol. Si pregunta por algo que no aparece
          (por ejemplo dinero, bodega o nómina), explica que no tienes esa información para su rol.
        - Los rangos y raciones son valores de referencia. Para enfermedades, tratamientos o mortalidad alta, recomienda
          consultar al asistente técnico o al veterinario, además de lo que puedas orientar.
        - No uses tablas ni Markdown con almohadillas o asteriscos. Usa frases cortas y, si hace falta, listas con guiones.
        - No registras nada en la aplicación: si quieren guardar un dato, indícales en qué pantalla hacerlo.

        Fichas de referencia de las especies:
        {$especies}
        TEXTO;
    }

    /** Estado actual de la finca, filtrado por el rol. */
    public static function para(User $usuario): string
    {
        $usuario->loadMissing('roles', 'tenant');
        $roles = $usuario->roles->pluck('nombre');
        $esPropietario = $usuario->tieneRol(Rol::PROPIETARIO);
        $esTecnico = $usuario->tieneRol(Rol::PROPIETARIO, Rol::ADMINISTRADOR);
        $veLagos = $usuario->tieneRol(Rol::PROPIETARIO, Rol::ADMINISTRADOR, Rol::TRABAJADOR);
        $esCelador = $usuario->tieneRol(Rol::CELADOR);

        $secciones = [
            "Finca: {$usuario->tenant->nombre}. Ahora: ".Region::ahora()->translatedFormat('l j \d\e F \d\e Y, H:i').'.',
            "Quien pregunta: {$usuario->name}, rol: {$roles->join(' y ')}.",
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
        if ($esPropietario) {
            $secciones[] = self::soloPropietario();
        }

        return "DATOS ACTUALES DE LA FINCA\n\n".implode("\n\n", array_filter($secciones));
    }

    private static function lagos(bool $conProduccion): string
    {
        $lagos = Lago::query()->with(['siembrasActivas', 'ultimaMedicion', 'aireacionActiva', 'tratamientosEnRetiro'])->orderBy('nombre')->get();
        if ($lagos->isEmpty()) {
            return 'Lagos: no hay lagos registrados.';
        }

        $lineas = $lagos->map(function (Lago $lago) use ($conProduccion) {
            $partes = ["Lago {$lago->nombre} ({$lago->tipo})"];

            if ($lago->siembrasActivas->isEmpty()) {
                $partes[] = 'sin peces';
            } elseif ($conProduccion) {
                foreach ($lago->siembrasActivas as $siembra) {
                    $partes[] = "{$siembra->nombre_especie}: ".Formato::numero($siembra->peces_vivos).' peces de '
                        .Formato::numero($siembra->peso_actual_g, 1).' g, '.Formato::numero($siembra->biomasa_kg, 1)." kg, {$siembra->dias_cultivo} días";
                }
                $racion = CalculadoraRacion::paraLago($lago);
                $partes[] = 'ración sugerida '.Formato::numero($racion['total_kg_dia'], 1)." kg/día en {$racion['tomas']} tomas"
                    .($racion['factor'] < 1 ? ' (reducida por la calidad del agua)' : '');
            } else {
                $partes[] = 'especies: '.$lago->siembrasActivas->pluck('nombre_especie')->join(', ');
            }

            if ($medicion = $lago->ultimaMedicion) {
                $valores = array_filter([
                    $medicion->oxigeno_mg_l !== null ? 'oxígeno '.Formato::numero($medicion->oxigeno_mg_l, 2).' mg/L' : null,
                    $medicion->temperatura_c !== null ? Formato::numero($medicion->temperatura_c, 1).' °C' : null,
                    $medicion->ph !== null ? 'pH '.Formato::numero($medicion->ph, 2) : null,
                ]);
                $partes[] = 'última medición '.$medicion->medido_at->format('d/m H:i').': '.implode(', ', $valores);
                foreach ($medicion->alertas($lago->siembrasActivas->pluck('especie')) as $alerta) {
                    $partes[] = "ALERTA: {$alerta}";
                }
            }
            $partes[] = $lago->aireacionActiva ? 'aireador encendido desde las '.$lago->aireacionActiva->encendido_at->format('H:i') : 'aireador apagado';
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
            ? "Está en turno de {$turno->rol} hasta las {$turno->termina_at->format('H:i')}."
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
            : "Novedades de seguridad (24 horas):\n".$novedades->map(fn (Ronda $r) => "- {$r->registrada_at->format('d/m H:i')} {$r->textoTipo()}"
                .($r->lago ? " en {$r->lago->nombre}" : '').": {$r->descripcion}")->implode("\n");
    }

    /** Bodega, dinero, personal y agenda: exclusivos del propietario. */
    private static function soloPropietario(): string
    {
        $bodega = CoberturaBodega::calcular()->map(fn ($item) => "- {$item['stock']->tipo_concentrado}: "
            .Formato::numero($item['stock']->cantidad_kg, 1).' kg'.($item['dias'] !== null ? ', alcanza unos '.Formato::numero($item['dias'], 1).' días' : '')
            .($item['estado'] !== CoberturaBodega::SUFICIENTE ? " ({$item['estado']})" : ''))->implode("\n");

        $recordatorios = EventoAgenda::query()->paraRecordar()->orderBy('inicia_at')->limit(10)->get()
            ->map(fn (EventoAgenda $e) => "- {$e->titulo} ({$e->textoTipo()}) el {$e->inicia_at->format('d/m')}".($e->estaVencido() ? ' VENCIDO' : ''))->implode("\n");

        return implode("\n\n", array_filter([
            'Bodega de alimento:'."\n".($bodega ?: '- vacía'),
            'Ventas del mes: '.Formato::dinero((int) Venta::where('fecha', '>=', Region::hoy()->startOfMonth()->toDateString())->sum('total'))
                .'. Por cobrar: '.Formato::dinero((int) Venta::conSaldo()->sum('saldo')).'.',
            'Solicitudes de personal por revisar: '.Permiso::query()->pendientes()->count().' permisos y '
                .HoraExtra::query()->pendientes()->count().' reportes de horas extra.',
            $recordatorios ? "Agenda pendiente:\n{$recordatorios}" : null,
        ]));
    }
}
