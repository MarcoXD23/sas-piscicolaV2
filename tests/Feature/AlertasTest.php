<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\DespachoAlimento;
use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\Mortalidad;
use App\Models\MovimientoAlimento;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AlertaCritica;
use App\Support\Alertas\CanalNotificacion;
use App\Support\Alertas\EvaluadorAlertas;
use App\Support\TenantActual;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AlertasTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $propietario;

    private Lago $lago;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-15 09:00', 'America/Bogota'));

        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->propietario = $this->persona(Rol::PROPIETARIO);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
    }

    private function persona(string $rol): User
    {
        return tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([$rol]);
    }

    private function sembrar(int $peces, string $especie = 'mojarra roja'): Siembra
    {
        return Siembra::create(['lago_id' => $this->lago->id, 'especie' => $especie, 'fecha' => '2026-09-01',
            'cantidad_alevinos' => $peces, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => $peces, 'peso_actual_g' => 100, 'biomasa_kg' => $peces / 10]);
    }

    private function salida(float $kg, string $fecha = '2026-10-15', string $concentrado = 'Engorde'): void
    {
        MovimientoAlimento::create(['user_id' => $this->propietario->id, 'lago_id' => $this->lago->id, 'fecha' => $fecha,
            'tipo_movimiento' => 'salida', 'tipo_concentrado' => $concentrado, 'cantidad_kg' => $kg]);
    }

    private function morir(Siembra $siembra, int $cantidad, string $fecha = '2026-10-15'): void
    {
        $siembra->decrement('peces_vivos', $cantidad);
        Mortalidad::create(['siembra_id' => $siembra->id, 'user_id' => $this->propietario->id, 'fecha' => $fecha, 'cantidad' => $cantidad]);
    }

    private function abiertas(?string $tipo = null): Collection
    {
        return Alerta::query()->abiertas()->when($tipo, fn ($q) => $q->where('tipo', $tipo))->get();
    }

    // --- Stock de alimento ---

    public function test_stock_bajo_segun_el_umbral_de_dias_editable_de_la_finca(): void
    {
        StockAlimento::create(['tipo_concentrado' => 'Engorde', 'cantidad_kg' => 50]);
        $this->salida(70, '2026-10-12'); // 10 kg/día en la ventana de 7 días → 50 kg alcanzan 5 días

        $alerta = $this->abiertas(Alerta::STOCK)->sole();
        $this->assertSame(Alerta::ADVERTENCIA, $alerta->severidad);
        $this->assertSame('Engorde alcanza para unos 5 días (el mínimo de la finca es 7).', $alerta->mensaje());

        // Con un umbral de 4 días, 5 días ya alcanzan: la alerta se cierra
        $this->finca->update(['alerta_stock_dias' => 4]);
        EvaluadorAlertas::paraFinca($this->finca->id);
        $this->assertCount(0, $this->abiertas(Alerta::STOCK));
        $this->assertNotNull($alerta->fresh()->cerrada_at);
    }

    public function test_stock_agotado_es_critico_y_se_avisa_una_vez_por_correo_solo_a_los_propietarios(): void
    {
        $otroPropietario = $this->persona(Rol::PROPIETARIO);
        $administrador = $this->persona(Rol::ADMINISTRADOR);
        StockAlimento::create(['tipo_concentrado' => 'Engorde', 'cantidad_kg' => 0]);

        EvaluadorAlertas::paraFinca($this->finca->id);
        EvaluadorAlertas::paraFinca($this->finca->id);

        $alerta = $this->abiertas(Alerta::STOCK)->sole();
        $this->assertTrue($alerta->esCritica());
        $this->assertNotNull($alerta->notificada_at);
        Notification::assertSentToTimes($this->propietario, AlertaCritica::class, 1);
        Notification::assertSentToTimes($otroPropietario, AlertaCritica::class, 1);
        Notification::assertNotSentTo($administrador, AlertaCritica::class);

        $correo = (new AlertaCritica($alerta))->toMail($this->propietario);
        $this->assertStringContainsString('Engorde está agotado.', implode(' ', $correo->introLines));
        $this->assertStringContainsString(__('alertas.tipos.stock'), $correo->subject);
    }

    // --- Mortalidad ---

    public function test_mortalidad_anormal_segun_porcentaje_y_periodo_editables(): void
    {
        $siembra = $this->sembrar(1000);

        $this->morir($siembra, 20); // 20 / 1000 = 2 %: por debajo del 3 % por defecto
        $this->assertCount(0, $this->abiertas(Alerta::MORTALIDAD));

        $this->morir($siembra, 15); // 35 / 1000 = 3,5 %
        $alerta = $this->abiertas(Alerta::MORTALIDAD)->sole();
        $this->assertSame((string) $this->lago->id, $alerta->referencia);
        $this->assertSame(Alerta::ADVERTENCIA, $alerta->severidad);
        $this->assertSame('Lago L01: 35 peces muertos en 7 días (3,5 %; el umbral de la finca es 3 %).', $alerta->mensaje());

        // Umbral más alto: se cierra
        $this->finca->update(['alerta_mortalidad_porcentaje' => 5]);
        EvaluadorAlertas::paraFinca($this->finca->id);
        $this->assertCount(0, $this->abiertas(Alerta::MORTALIDAD));
    }

    public function test_la_mortalidad_fuera_del_periodo_no_cuenta_y_el_doble_del_umbral_es_critico(): void
    {
        $siembra = $this->sembrar(1000);
        $this->morir($siembra, 50, '2026-10-01'); // hace 14 días: fuera del período de 7 días
        $this->assertCount(0, $this->abiertas(Alerta::MORTALIDAD));

        // Período de 15 días: ahora sí cuenta (50 de 1000 = 5 %)
        $this->finca->update(['alerta_mortalidad_dias' => 15]);
        EvaluadorAlertas::paraFinca($this->finca->id);
        $this->assertSame(Alerta::ADVERTENCIA, $this->abiertas(Alerta::MORTALIDAD)->sole()->severidad);

        $this->morir($siembra, 20); // 70 de 1000 = 7 % ≥ 6 % (doble del umbral)
        $alerta = $this->abiertas(Alerta::MORTALIDAD)->sole();
        $this->assertTrue($alerta->esCritica());
        Notification::assertSentToTimes($this->propietario, AlertaCritica::class, 1);
    }

    // --- Agua (misma fuente que el inicio, Seguridad & Noche y el asistente) ---

    public function test_agua_fuera_de_rango_usa_los_rangos_de_las_especies_y_el_oxigeno_bajo_es_critico(): void
    {
        $this->sembrar(500);

        MedicionAgua::create(['lago_id' => $this->lago->id, 'user_id' => $this->propietario->id, 'medido_at' => now(), 'amonio_mg_l' => 1.5]);
        $alerta = $this->abiertas(Alerta::AGUA)->sole();
        $this->assertSame(Alerta::ADVERTENCIA, $alerta->severidad);
        $this->assertSame('Lago L01: Amonio de 1,5 mg/L por encima de 1 mg/L.', $alerta->mensaje());
        Notification::assertNothingSent();

        // Nueva medición con oxígeno bajo: la misma alerta sigue abierta (no se repite) y pasa a crítica
        MedicionAgua::create(['lago_id' => $this->lago->id, 'user_id' => $this->propietario->id, 'medido_at' => now()->addMinute(), 'oxigeno_mg_l' => 2]);
        $this->assertSame($alerta->id, $this->abiertas(Alerta::AGUA)->sole()->id);
        $this->assertTrue($alerta->fresh()->esCritica());
        Notification::assertSentToTimes($this->propietario, AlertaCritica::class, 1);

        // Medición en rango: se cierra
        MedicionAgua::create(['lago_id' => $this->lago->id, 'user_id' => $this->propietario->id, 'medido_at' => now()->addMinutes(2), 'oxigeno_mg_l' => 6]);
        $this->assertCount(0, $this->abiertas(Alerta::AGUA));
    }

    // --- Historial: no se repite mientras siga abierta ---

    public function test_una_alerta_no_se_repite_mientras_siga_abierta_y_se_reabre_si_el_problema_vuelve(): void
    {
        StockAlimento::create(['tipo_concentrado' => 'Engorde', 'cantidad_kg' => 0]);

        foreach (range(1, 3) as $vez) {
            EvaluadorAlertas::paraFinca($this->finca->id);
        }
        $this->assertSame(1, Alerta::query()->count());

        // Llega alimento: se cierra
        StockAlimento::where('tipo_concentrado', 'Engorde')->update(['cantidad_kg' => 400]);
        EvaluadorAlertas::paraFinca($this->finca->id);
        $this->assertCount(0, $this->abiertas());

        // Se vuelve a agotar: alerta nueva en el historial (y nuevo aviso)
        StockAlimento::where('tipo_concentrado', 'Engorde')->update(['cantidad_kg' => 0]);
        EvaluadorAlertas::paraFinca($this->finca->id);
        $this->assertSame(2, Alerta::query()->count());
        $this->assertCount(1, $this->abiertas());
        Notification::assertSentToTimes($this->propietario, AlertaCritica::class, 2);
    }

    public function test_la_base_de_datos_impide_dos_alertas_abiertas_iguales(): void
    {
        Alerta::create(['tipo' => Alerta::STOCK, 'referencia' => 'Engorde', 'severidad' => Alerta::ADVERTENCIA, 'datos' => [], 'abierta_at' => now()]);

        $this->expectException(UniqueConstraintViolationException::class);
        Alerta::create(['tipo' => Alerta::STOCK, 'referencia' => 'Engorde', 'severidad' => Alerta::ADVERTENCIA, 'datos' => [], 'abierta_at' => now()]);
    }

    public function test_cada_finca_tiene_sus_propias_alertas(): void
    {
        StockAlimento::create(['tipo_concentrado' => 'Engorde', 'cantidad_kg' => 0]);
        EvaluadorAlertas::paraFinca($this->finca->id);

        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        EvaluadorAlertas::paraFinca($otra->id);

        app(TenantActual::class)->establecer($otra->id);
        $this->assertCount(0, Alerta::query()->get());
        app(TenantActual::class)->establecer($this->finca->id);
        $this->assertCount(1, Alerta::query()->get());
    }

    // --- Pantallas ---

    public function test_inicio_e_historial_muestran_las_alertas_y_la_diferencia_de_despacho_es_solo_del_propietario(): void
    {
        $administrador = $this->persona(Rol::ADMINISTRADOR);
        $trabajador = $this->persona(Rol::TRABAJADOR);
        StockAlimento::create(['tipo_concentrado' => 'Engorde', 'cantidad_kg' => 0]);
        $this->sembrar(500);
        MedicionAgua::create(['lago_id' => $this->lago->id, 'user_id' => $this->propietario->id, 'medido_at' => now(), 'amonio_mg_l' => 1.5]);
        // Despacho de 100 kg del que llegaron 80 kg: diferencia que solo ve el propietario
        $despacho = DespachoAlimento::create(['user_id' => $this->propietario->id, 'fecha' => '2026-10-14', 'proveedor' => 'P', 'tipo_concentrado' => 'Levante',
            'bultos' => 2, 'peso_bulto_kg' => 50, 'cantidad_kg' => 100, 'lote' => 'L']);
        MovimientoAlimento::create(['user_id' => $administrador->id, 'despacho_id' => $despacho->id, 'fecha' => '2026-10-15', 'tipo_movimiento' => 'entrada',
            'tipo_concentrado' => 'Levante', 'cantidad_kg' => 80, 'proveedor' => 'P', 'lote' => 'L', 'bultos' => 2, 'peso_bulto_kg' => 40]);
        $stock = 'Engorde está agotado.';
        $agua = 'Amonio de 1,5 mg/L por encima de 1 mg/L.';
        $diferencia = 'faltan 20 kg';

        $this->actingAs($this->propietario)->get(route('inicio'))->assertSee(__('alertas.inicio.titulo', ['cantidad' => 3]))->assertSee($stock)->assertSee($agua)->assertSee($diferencia);
        // El administrador recibe el concentrado: ve el stock, pero no la diferencia de lo que despachó el propietario
        $this->actingAs($administrador)->get(route('inicio'))->assertSee(__('alertas.inicio.titulo', ['cantidad' => 2]))->assertSee($stock)->assertSee($agua)->assertDontSee($diferencia);

        $this->actingAs($this->propietario)->get(route('admin.alertas.index'))->assertOk()->assertSee($stock)->assertSee($agua)->assertSee($diferencia);
        $this->actingAs($this->propietario)->get(route('admin.alertas.index', ['tipo' => Alerta::AGUA]))->assertDontSee($stock)->assertSee($agua);
        $this->actingAs($administrador)->get(route('admin.alertas.index'))->assertOk()->assertSee($stock)->assertDontSee($diferencia);
        $this->actingAs($administrador)->get(route('admin.alertas.index', ['tipo' => Alerta::DESPACHO_DIFERENCIA]))->assertSessionHasErrors('tipo');
        $this->actingAs($trabajador)->get(route('admin.alertas.index'))->assertForbidden();
    }

    public function test_los_umbrales_se_editan_en_ajustes_y_se_validan(): void
    {
        $datos = ['nombre' => 'Finca', 'pais' => 'CO', 'zona_horaria' => 'America/Bogota', 'idioma' => 'es'];

        $this->actingAs($this->propietario)->get(route('admin.ajustes.edit'))->assertSee(__('alertas.ajustes.titulo'));
        $this->actingAs($this->propietario)->put(route('admin.ajustes.update'), $datos + ['alerta_stock_dias' => 0, 'alerta_mortalidad_porcentaje' => 200, 'alerta_mortalidad_dias' => 90])
            ->assertSessionHasErrors(['alerta_stock_dias', 'alerta_mortalidad_porcentaje', 'alerta_mortalidad_dias']);

        $this->actingAs($this->propietario)->put(route('admin.ajustes.update'), $datos + ['alerta_stock_dias' => 10, 'alerta_mortalidad_porcentaje' => 2.5, 'alerta_mortalidad_dias' => 14])
            ->assertSessionHasNoErrors();
        $this->finca->refresh();
        $this->assertSame([10, 2.5, 14], [$this->finca->alerta_stock_dias, $this->finca->alerta_mortalidad_porcentaje, $this->finca->alerta_mortalidad_dias]);
    }

    public function test_el_comando_revisa_todas_las_fincas_activas(): void
    {
        StockAlimento::create(['tipo_concentrado' => 'Engorde', 'cantidad_kg' => 0]);

        $this->artisan('piscicola:evaluar-alertas')->assertSuccessful();

        $this->assertCount(1, $this->abiertas(Alerta::STOCK));
    }

    public function test_whatsapp_queda_como_canal_sin_proveedor(): void
    {
        $canales = collect(app()->tagged('canales_notificacion'));

        $this->assertCount(2, $canales);
        $this->assertTrue($canales->every(fn ($canal) => $canal instanceof CanalNotificacion));
        $this->assertSame([true, false], $canales->map->disponible()->all());
    }
}
