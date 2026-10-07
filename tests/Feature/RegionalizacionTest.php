<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Ronda;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\Tenant;
use App\Models\Turno;
use App\Models\User;
use App\Support\Formato;
use App\Support\Region;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegionalizacionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $bogota;

    private Tenant $madrid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bogota = Tenant::create(['nombre' => 'Finca Andina', 'codigo' => 'F01', 'pais' => 'CO', 'zona_horaria' => 'America/Bogota']);
        // En octubre Madrid está en UTC+2 y Bogotá en UTC−5: 7 horas de diferencia
        $this->madrid = Tenant::create(['nombre' => 'Finca Ibérica', 'codigo' => 'F02', 'pais' => 'ES', 'zona_horaria' => 'Europe/Madrid']);
    }

    private function en(Tenant $tenant): void
    {
        app(TenantActual::class)->establecer($tenant->id);
    }

    private function persona(Tenant $tenant, string ...$roles): User
    {
        return tap(User::factory()->create(['tenant_id' => $tenant->id]))->asignarRoles($roles);
    }

    private function crudo(string $tabla, int $id, string $columna): string
    {
        return (string) DB::table($tabla)->where('id', $id)->value($columna);
    }

    public function test_las_tablas_nuevas_de_finca_traen_zona_e_idioma_por_defecto(): void
    {
        $finca = Tenant::create(['nombre' => 'Nueva', 'codigo' => 'F03'])->fresh();

        $this->assertSame('America/Bogota', $finca->zona_horaria);
        $this->assertSame('es', $finca->idioma);
        $this->assertNull($finca->pais);
    }

    public function test_las_fechas_con_hora_se_guardan_en_utc(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 08:00', 'UTC'));
        $this->en($this->bogota);
        $celador = $this->persona($this->bogota, Rol::CELADOR);

        // Una hora escrita en la finca (Bogotá, UTC−5) se guarda 5 horas después en UTC
        $turno = Turno::create(['user_id' => $celador->id, 'rol' => Rol::CELADOR, 'inicia_at' => '2026-10-15 18:00', 'termina_at' => '2026-10-16 06:00']);
        $this->assertSame('2026-10-15 23:00:00', $this->crudo('turnos', $turno->id, 'inicia_at'));
        $this->assertSame('2026-10-16 11:00:00', $this->crudo('turnos', $turno->id, 'termina_at'));

        // Un instante (now) se guarda tal cual en UTC, sin importar la zona de la finca
        $ronda = Ronda::create(['user_id' => $celador->id, 'registrada_at' => now(), 'tipo' => Ronda::SIN_NOVEDAD]);
        $this->assertSame('2026-10-15 08:00:00', $this->crudo('rondas', $ronda->id, 'registrada_at'));
        $this->assertSame('2026-10-15 03:00', $ronda->fresh()->registrada_at->format('Y-m-d H:i'));
    }

    public function test_el_mismo_instante_se_muestra_en_la_zona_de_cada_finca(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 15:00', 'UTC'));

        foreach ([[$this->bogota, '10:00'], [$this->madrid, '17:00']] as [$finca, $horaLocal]) {
            $this->en($finca);
            $celador = $this->persona($finca, Rol::CELADOR);
            $ronda = Ronda::create(['user_id' => $celador->id, 'registrada_at' => now(), 'tipo' => Ronda::SIN_NOVEDAD]);

            $this->assertSame('2026-10-15 15:00:00', $this->crudo('rondas', $ronda->id, 'registrada_at'));
            $this->assertSame($horaLocal, $ronda->fresh()->registrada_at->format('H:i'));
            $this->assertSame($horaLocal, Region::ahora()->format('H:i'));
        }
    }

    public function test_turnos_de_06_a_14_en_dos_zonas_se_validan_con_la_hora_de_cada_finca(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 12:00', 'UTC'));
        $programar = function (Tenant $finca) {
            $this->en($finca);
            $propietario = $this->persona($finca, Rol::PROPIETARIO);
            $trabajador = $this->persona($finca, Rol::TRABAJADOR);
            $this->actingAs($propietario)->post(route('admin.turnos.store'), [
                'user_id' => $trabajador->id, 'rol' => Rol::TRABAJADOR, 'desde' => '2026-10-16', 'hasta' => '2026-10-16',
                'dias' => [5], 'hora_inicio' => '06:00', 'hora_fin' => '14:00',
            ])->assertSessionHasNoErrors();

            return $trabajador;
        };

        $deBogota = $programar($this->bogota);
        $deMadrid = $programar($this->madrid);

        // El mismo "06:00–14:00" son instantes distintos en UTC
        $this->assertSame('2026-10-16 11:00:00', DB::table('turnos')->where('user_id', $deBogota->id)->value('inicia_at'));
        $this->assertSame('2026-10-16 04:00:00', DB::table('turnos')->where('user_id', $deMadrid->id)->value('inicia_at'));

        // 07:00 UTC: en Madrid son las 09:00 (en turno); en Bogotá las 02:00 (fuera de turno)
        $this->travelTo(Carbon::parse('2026-10-16 07:00', 'UTC'));
        $this->en($this->madrid);
        $this->assertTrue($deMadrid->fresh()->puedeTrabajarComo(Rol::TRABAJADOR));
        $this->en($this->bogota);
        $this->assertFalse($deBogota->fresh()->puedeTrabajarComo(Rol::TRABAJADOR));

        // 13:00 UTC: en Bogotá son las 08:00 (en turno); en Madrid las 15:00 (ya terminó)
        $this->travelTo(Carbon::parse('2026-10-16 13:00', 'UTC'));
        $this->assertTrue($deBogota->fresh()->puedeTrabajarComo(Rol::TRABAJADOR));
        $this->en($this->madrid);
        $this->assertFalse($deMadrid->fresh()->puedeTrabajarComo(Rol::TRABAJADOR));

        // La pantalla de turnos muestra la hora local de cada finca
        $propietarioMadrid = User::withoutGlobalScopes()->where('tenant_id', $this->madrid->id)->whereHas('roles', fn ($r) => $r->where('nombre', Rol::PROPIETARIO))->first();
        $this->actingAs($propietarioMadrid)->get(route('admin.turnos.index', ['semana' => '2026-10-12']))->assertSee('06:00 – 14:00');
    }

    public function test_hoy_depende_de_la_zona_de_la_finca_tambien_al_validar(): void
    {
        // 03:00 UTC del 16: en Bogotá todavía es el 15; en Madrid ya es el 16
        $this->travelTo(Carbon::parse('2026-10-16 03:00', 'UTC'));

        $this->en($this->bogota);
        $this->assertSame('2026-10-15', Region::hoy()->toDateString());
        $this->en($this->madrid);
        $this->assertSame('2026-10-16', Region::hoy()->toDateString());

        $recibir = fn (Tenant $finca) => $this->actingAs($this->persona($finca, Rol::ADMINISTRADOR))->post(route('admin.bodega.recepciones.store'), [
            'fecha' => '2026-10-16', 'proveedor' => 'P', 'tipo_concentrado' => 'Engorde', 'lote' => 'L1', 'bultos' => 1, 'peso_bulto_kg' => 40,
        ]);

        $recibir($this->bogota)->assertInvalid(['fecha']);   // el 16 todavía es futuro en Bogotá
        $recibir($this->madrid)->assertSessionHasNoErrors();
        $this->assertSame(1, StockAlimento::withoutGlobalScopes()->count());
    }

    public function test_los_dias_de_cultivo_cuentan_dias_de_la_finca(): void
    {
        $this->travelTo(Carbon::parse('2026-10-16 03:00', 'UTC'));
        $this->en($this->bogota);
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $siembra = Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-10-05',
            'cantidad_alevinos' => 10, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 10, 'peso_actual_g' => 5, 'biomasa_kg' => 0.05]);

        $this->assertSame(10, $siembra->dias_cultivo); // del 5 al 15 de octubre en Bogotá
    }

    public function test_moneda_y_numeros_segun_el_pais_de_la_finca(): void
    {
        $this->en($this->bogota);
        $this->assertSame('$ 118.750', Formato::dinero(118750));
        $this->assertSame('1.234,5', Formato::numero(1234.5, 1));

        $this->madrid->update(['pais' => 'MX', 'zona_horaria' => 'America/Mexico_City']);
        $this->en($this->madrid);
        $this->assertSame('$118,750', Formato::dinero(118750));
        $this->assertSame('1,234.5', Formato::numero(1234.5, 1));
    }

    public function test_la_migracion_convierte_las_horas_existentes_de_bogota_a_utc_y_vuelve(): void
    {
        $this->en($this->bogota);
        $celador = $this->persona($this->bogota, Rol::CELADOR);
        $id = DB::table('rondas')->insertGetId(['tenant_id' => $this->bogota->id, 'user_id' => $celador->id,
            'registrada_at' => '2026-10-01 22:30:00', 'tipo' => Ronda::SIN_NOVEDAD]);

        $migracion = require database_path('migrations/2026_10_14_000002_convert_timestamps_to_utc.php');
        $migracion->up();
        $this->assertSame('2026-10-02 03:30:00', $this->crudo('rondas', $id, 'registrada_at'));

        $migracion->down();
        $this->assertSame('2026-10-01 22:30:00', $this->crudo('rondas', $id, 'registrada_at'));
    }

    public function test_ajustes_de_la_finca_solo_para_el_propietario_y_con_valores_validos(): void
    {
        $this->en($this->bogota);
        $propietario = $this->persona($this->bogota, Rol::PROPIETARIO);
        $administrador = $this->persona($this->bogota, Rol::ADMINISTRADOR);

        $this->actingAs($administrador)->get(route('admin.ajustes.edit'))->assertForbidden();
        $this->actingAs($propietario)->get(route('admin.ajustes.edit'))->assertOk()->assertSee(__('region.ajustes.titulo'));

        $datos = ['nombre' => 'Finca Andina', 'pais' => 'PE', 'zona_horaria' => 'America/Lima', 'idioma' => 'es'];
        $this->actingAs($propietario)->put(route('admin.ajustes.update'), ['pais' => 'XX'] + $datos)->assertInvalid(['pais']);
        $this->actingAs($propietario)->put(route('admin.ajustes.update'), ['zona_horaria' => 'Marte/Base'] + $datos)->assertInvalid(['zona_horaria']);
        $this->actingAs($propietario)->put(route('admin.ajustes.update'), ['idioma' => 'fr'] + $datos)->assertInvalid(['idioma']);

        $this->actingAs($propietario)->put(route('admin.ajustes.update'), $datos)->assertSessionHasNoErrors();
        $this->bogota->refresh();
        $this->assertSame(['PE', 'America/Lima'], [$this->bogota->pais, $this->bogota->zona_horaria]);

        // Cada finca cambia solo su propia región
        $this->assertSame('Europe/Madrid', $this->madrid->fresh()->zona_horaria);
    }

    public function test_no_quedan_menciones_de_un_solo_pais_en_el_codigo_ni_en_las_vistas(): void
    {
        $archivos = array_merge(
            glob(app_path('{,*/,*/*/,*/*/*/}*.php'), GLOB_BRACE),
            glob(resource_path('views/{,*/,*/*/,*/*/*/}*.blade.php'), GLOB_BRACE),
            glob(lang_path('es/*.php')),
        );

        foreach ($archivos as $archivo) {
            $contenido = file_get_contents($archivo);
            foreach (['Tolima', 'Colombia', 'Bogotá', ' ICA', 'pesos colombianos', 'Magdalena-Cauca', 'es-CO'] as $regional) {
                $this->assertStringNotContainsString($regional, $contenido, "«{$regional}» aparece en {$archivo}");
            }
        }
    }
}
