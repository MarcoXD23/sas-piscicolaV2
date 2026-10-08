<?php

namespace Tests\Feature;

use App\Models\Abono;
use App\Models\AceptacionLegal;
use App\Models\Aireacion;
use App\Models\Alerta;
use App\Models\AvisoPesca;
use App\Models\Bitacora;
use App\Models\Concerns\PerteneceATenant;
use App\Models\Cosecha;
use App\Models\DespachoAlimento;
use App\Models\Especie;
use App\Models\EspecieFoto;
use App\Models\EventoAgenda;
use App\Models\Exportacion;
use App\Models\HoraExtra;
use App\Models\Lago;
use App\Models\Liquidacion;
use App\Models\MedicionAgua;
use App\Models\Mortalidad;
use App\Models\MovimientoAlimento;
use App\Models\Muestreo;
use App\Models\Permiso;
use App\Models\PesajeCosecha;
use App\Models\Rol;
use App\Models\RegistroSinConexion;
use App\Models\Ronda;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\Tarea;
use App\Models\Tenant;
use App\Models\Traslado;
use App\Models\Tratamiento;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use App\Support\TenantActual;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route as Rutas;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pruebas generales de aislamiento entre fincas:
 * - todos los modelos con PerteneceATenant: una finca no ve los registros de la otra;
 * - todas las rutas con ids: el id de otra finca devuelve 404.
 * Si se agrega un modelo o una ruta con id, estas pruebas obligan a incluirlo.
 */
class AislamientoFincasTest extends TestCase
{
    use RefreshDatabase;

    /** Parámetro de ruta => modelo de la finca. */
    private const PARAMETROS = [
        'lago' => Lago::class, 'siembra' => Siembra::class, 'aviso' => AvisoPesca::class, 'despacho' => DespachoAlimento::class,
        'venta' => Venta::class, 'tarea' => Tarea::class, 'turno' => Turno::class, 'evento' => EventoAgenda::class,
        'liquidacion' => Liquidacion::class, 'permiso' => Permiso::class, 'horaExtra' => HoraExtra::class,
        'aireacion' => Aireacion::class, 'usuario' => User::class, 'cosecha' => Cosecha::class, 'especie' => Especie::class, 'foto' => EspecieFoto::class, 'exportacion' => Exportacion::class,
    ];

    /** Datos válidos para rutas que validan el formulario antes de buscar el registro (sin ellos responderían 302). */
    private const DATOS = [
        'admin.personal.update' => ['roles' => [Rol::TRABAJADOR]],
    ];

    /** Parámetros que no son registros de una finca (token de contraseña, ficha de especie, panel de plataforma). */
    private const NO_SON_DE_FINCA = ['token', 'slug', 'finca'];

    /** Parámetros que no son registros (van junto a un id de otra finca, que es lo que se prueba). */
    private const VALORES_FIJOS = ['version' => 'ligera'];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-15 09:00', 'America/Bogota'));
    }

    /** @return list<class-string<Model>> Modelos que usan el filtro global por finca */
    private function modelosDeFinca(): array
    {
        $modelos = [];
        foreach (glob(app_path('Models/*.php')) as $archivo) {
            $clase = 'App\\Models\\'.basename($archivo, '.php');
            if (class_exists($clase) && in_array(PerteneceATenant::class, class_uses_recursive($clase), true)) {
                $modelos[] = $clase;
            }
        }
        sort($modelos);

        return $modelos;
    }

    /**
     * Un registro de cada modelo de la finca.
     *
     * @return array<class-string<Model>, Model>
     */
    private function poblar(Tenant $finca, string $sufijo): array
    {
        app(TenantActual::class)->establecer($finca->id);
        $persona = tap(User::factory()->create(['tenant_id' => $finca->id, 'name' => "Persona {$sufijo}"]))->asignarRoles([Rol::TRABAJADOR, Rol::CELADOR]);
        $lago = Lago::create(['nombre' => "Lago {$sufijo}", 'tipo' => 'tierra']);
        $otroLago = Lago::create(['nombre' => "Destino {$sufijo}", 'tipo' => 'tierra']);
        $siembra = Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-09-01', 'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 10, 'peces_vivos' => 1000, 'peso_actual_g' => 200, 'biomasa_kg' => 200]);
        $destino = Siembra::create(['lago_id' => $otroLago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-10-01', 'cantidad_alevinos' => 10,
            'peso_promedio_inicial_g' => 200, 'peces_vivos' => 10, 'peso_actual_g' => 200, 'biomasa_kg' => 2]);
        $cosecha = Cosecha::create(['siembra_id' => $siembra->id, 'lago_id' => $lago->id, 'user_id' => $persona->id, 'fecha' => '2026-10-10',
            'peces' => 10, 'kg' => 2, 'peso_promedio_g' => 200]);
        $venta = Venta::create(['cosecha_id' => $cosecha->id, 'user_id' => $persona->id, 'cliente' => "Cliente {$sufijo}", 'fecha' => '2026-10-11',
            'kg' => 1, 'precio_kg' => 1000, 'total' => 1000, 'forma_pago' => 'credito', 'saldo' => 1000]);
        $liquidacion = Liquidacion::create(['user_id' => $persona->id, 'creada_por' => $persona->id, 'semana_inicio' => '2026-10-05', 'semana_fin' => '2026-10-11',
            'horas_turnos' => 8, 'valor_hora' => 100, 'valor_turnos' => 800, 'horas_extra' => 0, 'valor_hora_extra' => 150, 'valor_horas_extra' => 0,
            'bruto' => 800, 'descuento_pescado' => 0, 'total_pagar' => 800]);
        $despacho = DespachoAlimento::create(['user_id' => $persona->id, 'fecha' => '2026-10-12', 'proveedor' => "Proveedor {$sufijo}", 'tipo_concentrado' => 'Engorde',
            'bultos' => 1, 'peso_bulto_kg' => 40, 'cantidad_kg' => 40, 'lote' => 'L']);

        return [
            User::class => $persona,
            Lago::class => $lago,
            Siembra::class => $siembra,
            Cosecha::class => $cosecha,
            Venta::class => $venta,
            Liquidacion::class => $liquidacion,
            DespachoAlimento::class => $despacho,
            Abono::class => Abono::create(['venta_id' => $venta->id, 'user_id' => $persona->id, 'fecha' => '2026-10-12', 'valor' => 100, 'medio' => 'efectivo']),
            Aireacion::class => Aireacion::create(['lago_id' => $lago->id, 'user_id' => $persona->id, 'encendido_at' => now()]),
            Alerta::class => Alerta::create(['tipo' => Alerta::AGUA, 'referencia' => (string) $lago->id, 'severidad' => Alerta::ADVERTENCIA, 'datos' => [], 'abierta_at' => now()]),
            AvisoPesca::class => AvisoPesca::create(['lago_id' => $lago->id, 'siembra_id' => $siembra->id, 'user_id' => $persona->id, 'estado' => AvisoPesca::PENDIENTE, 'reportado_at' => now()]),
            Bitacora::class => Bitacora::registrar($persona, 'ronda', $lago, [Rol::CELADOR], ['tipo' => 'x', 'descripcion' => '']),
            EventoAgenda::class => EventoAgenda::create(['creado_por' => $persona->id, 'titulo' => "Evento {$sufijo}", 'tipo' => 'visita', 'inicia_at' => '2026-10-20 08:00', 'recordar_dias_antes' => 0]),
            HoraExtra::class => HoraExtra::create(['user_id' => $persona->id, 'fecha' => '2026-10-14', 'horas' => 2, 'motivo' => 'm', 'estado' => 'pendiente']),
            MedicionAgua::class => MedicionAgua::create(['lago_id' => $lago->id, 'user_id' => $persona->id, 'medido_at' => now(), 'oxigeno_mg_l' => 6]),
            Mortalidad::class => Mortalidad::create(['siembra_id' => $siembra->id, 'user_id' => $persona->id, 'fecha' => '2026-10-12', 'cantidad' => 1]),
            MovimientoAlimento::class => MovimientoAlimento::create(['user_id' => $persona->id, 'lago_id' => $lago->id, 'fecha' => '2026-10-12', 'tipo_movimiento' => 'salida', 'tipo_concentrado' => 'Engorde', 'cantidad_kg' => 1]),
            Muestreo::class => Muestreo::create(['siembra_id' => $siembra->id, 'user_id' => $persona->id, 'fecha' => '2026-10-12', 'peces_pesados' => 1, 'peso_total_g' => 200, 'peso_promedio_g' => 200]),
            Permiso::class => Permiso::create(['user_id' => $persona->id, 'fecha_inicio' => '2026-10-20', 'fecha_fin' => '2026-10-21', 'motivo' => 'm', 'estado' => 'pendiente']),
            PesajeCosecha::class => PesajeCosecha::create(['cosecha_id' => $cosecha->id, 'numero' => 1, 'tipo' => 'canasta', 'canastas' => 1, 'tara_unitaria_kg' => 1, 'bruto_kg' => 3, 'tara_kg' => 1, 'neto_kg' => 2]),
            AceptacionLegal::class => AceptacionLegal::registrar($persona),
            Exportacion::class => Exportacion::create(['user_id' => $persona->id, 'estado' => Exportacion::LISTA, 'ruta' => "x/{$sufijo}.zip", 'bytes' => 1, 'vence_at' => now()->addDay()]),
            Especie::class => $especie = Especie::create(['nombre' => "Especie {$sufijo}", 'clima' => Especie::CALIDO]),
            EspecieFoto::class => EspecieFoto::create(['especie_id' => $especie->id, 'user_id' => $persona->id, 'ruta_original' => "x/{$sufijo}.jpg", 'ruta_ligera' => "x/{$sufijo}.webp",
                'tipo_original' => 'image/jpeg', 'bytes_original' => 10, 'bytes_ligera' => 5, 'ancho' => 1, 'alto' => 1, 'orden' => 1]),
            RegistroSinConexion::class => RegistroSinConexion::create(['user_id' => $persona->id, 'uuid' => (string) Str::uuid(), 'ruta' => 'noche.rondas.store', 'registrado_at' => now()]),
            Ronda::class => Ronda::create(['user_id' => $persona->id, 'registrada_at' => now(), 'tipo' => Ronda::SIN_NOVEDAD]),
            StockAlimento::class => StockAlimento::create(['tipo_concentrado' => "Concentrado {$sufijo}", 'cantidad_kg' => 10]),
            Tarea::class => Tarea::create(['asignada_a' => $persona->id, 'creada_por' => $persona->id, 'titulo' => "Tarea {$sufijo}", 'fecha' => '2026-10-20']),
            Traslado::class => Traslado::create(['siembra_origen_id' => $siembra->id, 'siembra_destino_id' => $destino->id, 'lago_origen_id' => $lago->id, 'lago_destino_id' => $otroLago->id,
                'user_id' => $persona->id, 'fecha' => '2026-10-12', 'cantidad' => 1, 'peso_promedio_g' => 200, 'biomasa_kg' => 0.2]),
            Tratamiento::class => Tratamiento::create(['lago_id' => $lago->id, 'user_id' => $persona->id, 'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-02',
                'motivo' => 'm', 'producto' => 'p', 'dosis' => 'd', 'via' => 'agua', 'dias_retiro' => 0]),
            Turno::class => Turno::create(['user_id' => $persona->id, 'rol' => Rol::TRABAJADOR, 'inicia_at' => '2026-10-20 06:00', 'termina_at' => '2026-10-20 14:00']),
        ];
    }

    public function test_ninguna_finca_ve_los_registros_de_otra_en_ningun_modelo(): void
    {
        $fincaA = Tenant::create(['nombre' => 'Finca A', 'codigo' => 'FA']);
        $fincaB = Tenant::create(['nombre' => 'Finca B', 'codigo' => 'FB']);
        $deA = $this->poblar($fincaA, 'A');
        $deB = $this->poblar($fincaB, 'B');

        // Todos los modelos con el filtro de finca están cubiertos (un modelo nuevo obliga a agregarlo aquí)
        $cubiertos = array_keys($deA);
        sort($cubiertos);
        $this->assertSame($this->modelosDeFinca(), $cubiertos);

        foreach ([[$fincaA, $deA, $deB], [$fincaB, $deB, $deA]] as [$finca, $propios, $ajenos]) {
            app(TenantActual::class)->establecer($finca->id);
            foreach ($propios as $modelo => $registro) {
                $visibles = $modelo::query()->pluck((new $modelo)->getKeyName())->all();
                $this->assertContains($registro->getKey(), $visibles, "{$modelo}: la finca no ve su propio registro");
                $this->assertNotContains($ajenos[$modelo]->getKey(), $visibles, "{$modelo}: la finca {$finca->codigo} ve un registro de otra finca");
                $this->assertNull($modelo::query()->find($ajenos[$modelo]->getKey()), "{$modelo}: find() encuentra un registro de otra finca");
                $this->assertSame(
                    $modelo::withoutGlobalScopes()->where('tenant_id', $finca->id)->count(),
                    $modelo::query()->count(),
                    "{$modelo}: el conteo incluye registros de otra finca",
                );
            }
        }
    }

    public function test_toda_ruta_con_id_de_otra_finca_devuelve_404(): void
    {
        $fincaA = Tenant::create(['nombre' => 'Finca A', 'codigo' => 'FA']);
        $fincaB = Tenant::create(['nombre' => 'Finca B', 'codigo' => 'FB']);
        $this->poblar($fincaA, 'A');
        $ajenos = $this->poblar($fincaB, 'B');
        app(TenantActual::class)->establecer($fincaA->id);

        // Alguien de la finca A con los cuatro roles y en turno: ningún rol ni turno lo frena antes de buscar el registro
        $todo = tap(User::factory()->create(['tenant_id' => $fincaA->id]))->asignarRoles(Rol::TODOS);
        $this->ponerEnTurno($todo, Rol::TRABAJADOR);
        $this->ponerEnTurno($todo, Rol::CELADOR);

        $revisadas = 0;
        foreach (Rutas::getRoutes()->getRoutes() as $ruta) {
            /** @var Route $ruta */
            $parametros = $ruta->parameterNames();
            if ($parametros === [] || str_starts_with($ruta->uri(), 'plataforma') || array_intersect($parametros, self::NO_SON_DE_FINCA) !== []) {
                continue;
            }

            $valores = [];
            foreach ($parametros as $parametro) {
                if (isset(self::VALORES_FIJOS[$parametro])) {
                    $valores[$parametro] = self::VALORES_FIJOS[$parametro];

                    continue;
                }
                $this->assertArrayHasKey($parametro, self::PARAMETROS, "La ruta {$ruta->uri()} usa {{$parametro}}: agrégalo a PARAMETROS");
                $valores[$parametro] = $ajenos[self::PARAMETROS[$parametro]]->getKey();
            }

            $metodo = strtolower($ruta->methods()[0]);
            $respuesta = $this->actingAs($todo)->{$metodo}(route($ruta->getName(), $valores), self::DATOS[$ruta->getName()] ?? []);
            $this->assertSame(404, $respuesta->status(), "{$metodo} {$ruta->uri()} con un id de otra finca devolvió {$respuesta->status()}");
            $revisadas++;
        }

        $this->assertGreaterThan(15, $revisadas);
    }
}
