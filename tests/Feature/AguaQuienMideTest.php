<?php

namespace Tests\Feature;

use App\Http\Middleware\RegistroSinConexion as Middleware;
use App\Models\AjusteFinca;
use App\Models\Bitacora;
use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Ajustes\CatalogoAjustes;
use App\Support\Campo\PaqueteTrabajo;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * F4: el ajuste «Quién mide el agua» decide quién registra mediciones (con y sin conexión). El servidor manda siempre:
 * aunque una pantalla vieja muestre el botón, el rol que no mide recibe 403 con el motivo.
 */
class AguaQuienMideTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private Lago $lago;

    private User $administrador;

    private User $celador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finca = Tenant::create(['nombre' => 'Finca Norte', 'codigo' => 'F01', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->administrador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::ADMINISTRADOR]);
        $this->celador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::CELADOR]);
        $this->ponerEnTurno($this->celador, Rol::CELADOR);
    }

    private function quienMide(string $valor): void
    {
        AjusteFinca::query()->updateOrCreate(['clave' => CatalogoAjustes::AGUA_QUIEN_MIDE], ['valor' => $valor]);
    }

    /** Medición con señal: el administrador desde el detalle del lago y el celador desde Seguridad & Noche. */
    private function medirConSenal(User $usuario): TestResponse
    {
        // Sin las cabeceras de sin conexión de una petición anterior (withHeaders las deja puestas)
        $this->flushHeaders();

        return $usuario->is($this->administrador)
            ? $this->actingAs($usuario)->post(route('admin.mediciones-agua.store', $this->lago), ['medido_at' => now()->setTimezone('America/Bogota')->subMinute()->format('Y-m-d H:i:s'), 'oxigeno_mg_l' => 5.2])
            : $this->actingAs($usuario)->post(route('noche.mediciones.store', $this->lago), ['oxigeno_mg_l' => 5.2]);
    }

    /** Medición sin conexión, como la envía la cola de Registro rápido (registrada hace 30 min, reloj desfasado). */
    private function medirSinConexion(User $usuario, int $relojTelefono = 0): TestResponse
    {
        $this->flushHeaders();
        $ruta = $usuario->is($this->administrador) ? 'admin.mediciones-agua.store' : 'noche.mediciones.store';
        $momento = now()->subMinutes(30);

        return $this->actingAs($usuario)->withHeaders([
            'Accept' => 'application/json',
            Middleware::CABECERA_ID => (string) Str::uuid(),
            Middleware::CABECERA_MOMENTO => $momento->copy()->addMinutes($relojTelefono)->toIso8601String(),
            Middleware::CABECERA_HORA_TELEFONO => now()->addMinutes($relojTelefono)->toIso8601String(),
            Middleware::CABECERA_ULTIMA_HORA_SERVIDOR => $momento->copy()->subMinute()->utc()->toIso8601String(),
        ])->post(route($ruta, $this->lago), ['temperatura_c' => '26.5']);
    }

    private function secciones(User $usuario): array
    {
        $this->flushHeaders();

        return $this->actingAs($usuario)->getJson(route('campo.paquete'))->assertOk()->json('secciones');
    }

    public function test_por_defecto_miden_los_dos_y_el_administrador_tambien_sin_conexion(): void
    {
        $this->medirConSenal($this->administrador)->assertSessionHasNoErrors()->assertRedirect();
        $this->medirConSenal($this->celador)->assertSessionHasNoErrors()->assertRedirect();

        // Administrador sin conexión, con el reloj del teléfono 90 minutos atrasado: se corrige y queda anotado
        $this->medirSinConexion($this->administrador, relojTelefono: -90)->assertCreated()->assertJson(['estado' => 'guardado']);
        $this->assertSame(3, MedicionAgua::count());

        $registro = Bitacora::query()->where('accion', 'medicion')->latest('id')->first();
        $this->assertSame('administrador', $registro->rol);
        $this->assertSame(now()->subMinutes(30)->setTimezone('America/Bogota')->format('Y-m-d H:i'), $registro->momentoLocal()->format('Y-m-d H:i'));
        $this->assertStringContainsString('registrado sin conexión', $registro->textoDetalle());
        $this->assertStringContainsString(__('bitacora.reloj_corregido', ['minutos' => 90]), $registro->textoDetalle());

        // Registro rápido: los dos tienen la sección, cada uno con su ruta; el administrador sin turno
        $paquete = $this->actingAs($this->administrador)->getJson(route('campo.paquete'))->json();
        $this->assertContains(PaqueteTrabajo::MEDICION, $paquete['secciones']);
        $this->assertSame(route('admin.mediciones-agua.store', $this->lago, absolute: false), $paquete['lagos'][0]['url_medicion']);
        $this->assertFalse($paquete['exige_turno'][PaqueteTrabajo::MEDICION]);
        $paqueteCelador = $this->actingAs($this->celador)->getJson(route('campo.paquete'))->json();
        $this->assertSame(route('noche.mediciones.store', $this->lago, absolute: false), $paqueteCelador['lagos'][0]['url_medicion']);
        $this->assertTrue($paqueteCelador['exige_turno'][PaqueteTrabajo::MEDICION]);
    }

    public function test_solo_el_celador_el_administrador_no_mide_ni_ve_el_boton(): void
    {
        $this->quienMide(CatalogoAjustes::QUIEN_MIDE_CELADOR);

        $this->medirConSenal($this->administrador)->assertForbidden();
        // Sin conexión (un registro que ya estaba en la cola): 403 con el motivo, queda en «Rechazados»
        $this->medirSinConexion($this->administrador)->assertForbidden()->assertJson(['message' => __('mensajes.medicion_agua.quien_mide.celador')]);
        $this->assertSame(0, MedicionAgua::count());

        $this->actingAs($this->administrador)->get(route('admin.lagos.show', $this->lago))->assertOk()
            ->assertDontSee('data-abrir-modal="modal-medicion"', false)
            ->assertSee(__('mensajes.medicion_agua.quien_mide.celador'));
        $this->assertNotContains(PaqueteTrabajo::MEDICION, $this->secciones($this->administrador));

        // El celador sigue igual
        $this->medirConSenal($this->celador)->assertSessionHasNoErrors()->assertRedirect();
        $this->medirSinConexion($this->celador)->assertCreated();
        $this->assertContains(PaqueteTrabajo::MEDICION, $this->secciones($this->celador));
    }

    public function test_solo_el_administrador_el_celador_no_mide_ni_ve_el_boton(): void
    {
        $this->quienMide(CatalogoAjustes::QUIEN_MIDE_ADMINISTRADOR);

        $this->medirConSenal($this->celador)->assertForbidden();
        $this->medirSinConexion($this->celador)->assertForbidden()->assertJson(['message' => __('mensajes.medicion_agua.quien_mide.administrador')]);
        $this->assertSame(0, MedicionAgua::count());

        $this->actingAs($this->celador)->get(route('noche.index'))->assertOk()
            ->assertDontSee('data-abrir-modal="modal-oxigeno-'.$this->lago->id.'"', false)
            ->assertSee(__('mensajes.medicion_agua.quien_mide.administrador'));
        // El celador sigue con su ronda, sin la medición
        $this->assertSame([PaqueteTrabajo::RONDA], $this->secciones($this->celador));

        // El administrador mide con y sin conexión
        $this->medirConSenal($this->administrador)->assertSessionHasNoErrors()->assertRedirect();
        $this->medirSinConexion($this->administrador)->assertCreated();
        $this->assertSame(2, MedicionAgua::count());
        $this->actingAs($this->administrador)->get(route('admin.lagos.show', $this->lago))->assertSee('data-abrir-modal="modal-medicion"', false);
    }

    public function test_el_ajuste_de_una_finca_no_afecta_a_otra(): void
    {
        $this->quienMide(CatalogoAjustes::QUIEN_MIDE_ADMINISTRADOR);

        $otra = Tenant::create(['nombre' => 'Finca Sur', 'codigo' => 'F02', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($otra->id);
        $lagoOtra = Lago::create(['nombre' => 'S01', 'tipo' => 'tierra']);
        $celadorOtra = tap(User::factory()->create(['tenant_id' => $otra->id]))->asignarRoles([Rol::CELADOR]);
        $this->ponerEnTurno($celadorOtra, Rol::CELADOR);

        // En la otra finca vale el valor por defecto: el celador mide
        $this->actingAs($celadorOtra)->post(route('noche.mediciones.store', $lagoOtra), ['oxigeno_mg_l' => 5])->assertSessionHasNoErrors();
        $this->assertSame(1, MedicionAgua::count());

        // Y en la primera, el celador sigue sin poder
        app(TenantActual::class)->establecer($this->finca->id);
        $this->medirConSenal($this->celador)->assertForbidden();
    }
}
