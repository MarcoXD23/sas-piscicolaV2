<?php

namespace Tests\Feature;

use App\Http\Middleware\RegistroSinConexion;
use App\Models\AjusteFinca;
use App\Models\Bitacora;
use App\Models\Ingreso;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Ajustes\CatalogoAjustes;
use App\Support\Seguridad\RegistroIngresos;
use App\Support\TenantActual;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ajustes › Seguridad de la finca (solo propietario): verificación en dos pasos obligatoria para propietario y
 * administrador, cerrar la sesión de todo el personal e ingresos del personal.
 */
class SeguridadFincaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $propietario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finca = Tenant::create(['nombre' => 'Finca Norte', 'codigo' => 'NORTE']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->propietario = $this->persona(Rol::PROPIETARIO);
    }

    private function persona(string $rol, ?Tenant $finca = null): User
    {
        return tap(User::factory()->create(['tenant_id' => ($finca ?? $this->finca)->id, 'password' => 'Clave-Segura-123']))->asignarRoles([$rol]);
    }

    private function exigirDosPasos(): void
    {
        AjusteFinca::query()->updateOrCreate(['clave' => CatalogoAjustes::SEGURIDAD_DOS_PASOS], ['valor' => CatalogoAjustes::ENCENDIDA]);
    }

    private function sesion(User $usuario, string $id): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $usuario->id, 'ip_address' => '190.24.8.77', 'user_agent' => 'Mozilla/5.0 (Linux; Android 14) Chrome/130.0 Mobile Safari/537.36',
            'payload' => '', 'last_activity' => now()->getTimestamp()]);
    }

    public function test_el_propietario_enciende_la_verificacion_obligatoria_y_queda_en_la_bitacora(): void
    {
        $campo = CatalogoAjustes::campo(CatalogoAjustes::SEGURIDAD_DOS_PASOS);
        // Solo el propietario la cambia
        $administrador = $this->persona(Rol::ADMINISTRADOR);
        $this->actingAs($administrador)->put(route('admin.ajustes.seguridad.guardar'), [$campo => 'encendida'])->assertForbidden();

        $this->actingAs($this->propietario)->get(route('admin.ajustes.edit'))->assertOk()
            ->assertSee('id="seguridad"', false)->assertSee(__('seguridad.indice.seguridad'))->assertSee(__('ajustes_finca.seguridad.dos_pasos_obligatoria.etiqueta'));

        // El propietario sin dos pasos no queda atrapado al encenderla: lo lleva a activarla
        $this->actingAs($this->propietario)->put(route('admin.ajustes.seguridad.guardar'), [$campo => 'encendida'])->assertRedirect();
        $this->assertSame('encendida', AjusteFinca::query()->where('clave', CatalogoAjustes::SEGURIDAD_DOS_PASOS)->value('valor'));
        $this->assertStringContainsString('Encendida', Bitacora::query()->where('accion', 'ajustes')->sole()->textoDetalle());
        $this->actingAs($this->propietario)->get(route('inicio'))->assertRedirect(route('mi-cuenta.dos-pasos.activar'));
    }

    public function test_obligatoria_lleva_a_activarla_al_propietario_y_al_administrador_y_no_al_trabajador_ni_al_celador(): void
    {
        $this->exigirDosPasos();
        $administrador = $this->persona(Rol::ADMINISTRADOR);

        foreach ([$this->propietario, $administrador] as $persona) {
            $this->actingAs($persona)->get(route('inicio'))->assertRedirect(route('mi-cuenta.dos-pasos.activar'));
            $this->actingAs($persona)->get(route('admin.lagos.index'))->assertRedirect(route('mi-cuenta.dos-pasos.activar'));
            // Peticiones JSON y la cola sin conexión: 401 (la cola no pierde el registro, pide entrar de nuevo)
            $this->actingAs($persona)->getJson(route('campo.paquete'))->assertStatus(401)->assertJson(['dos_pasos_obligatoria' => true]);
            $this->actingAs($persona)->withHeaders([RegistroSinConexion::CABECERA_ID => (string) Str::uuid()])
                ->post(route('admin.lagos.store'), ['nombre' => 'L09', 'tipo' => 'tierra'])->assertStatus(401);
            $this->flushHeaders();
        }

        foreach ([Rol::TRABAJADOR, Rol::CELADOR] as $rol) {
            $this->actingAs($this->persona($rol))->get(route('inicio'))->assertOk();
        }

        // Apagada (el valor por defecto): nadie es llevado
        AjusteFinca::query()->delete();
        $this->actingAs($administrador)->get(route('inicio'))->assertOk();
    }

    public function test_un_administrador_obligado_no_queda_atrapado(): void
    {
        $this->exigirDosPasos();
        $administrador = $this->persona(Rol::ADMINISTRADOR);

        // Puede activarla, ver sus códigos y cerrar sesión
        $this->actingAs($administrador)->get(route('mi-cuenta.dos-pasos.activar'))->assertOk();
        $clave = session('dos_pasos_clave_nueva');
        $this->actingAs($administrador)->post(route('mi-cuenta.dos-pasos.confirmar'), ['codigo' => Totp::codigo($clave, Totp::pasoActual())])
            ->assertRedirect(route('mi-cuenta.dos-pasos.codigos'));
        $recuperacion = session('dos_pasos_codigos')[0];
        $this->actingAs($administrador)->get(route('mi-cuenta.dos-pasos.codigos'))->assertOk();

        // Con los dos pasos activos ya entra a todo, y no los puede quitar mientras la finca los exija
        $this->actingAs($administrador->fresh())->get(route('inicio'))->assertOk();
        $this->actingAs($administrador->fresh())->get(route('mi-cuenta.index'))->assertOk()->assertSee(__('seguridad.dos_pasos_obligatoria.no_se_quita'));
        $this->actingAs($administrador->fresh())->delete(route('mi-cuenta.dos-pasos.desactivar'), ['actual' => 'Clave-Segura-123', 'codigo' => $recuperacion])
            ->assertSessionHasErrors(['codigo' => __('seguridad.dos_pasos_obligatoria.no_se_quita')]);
        $this->assertTrue($administrador->fresh()->tieneDosPasos());

        // Cerrar sesión siempre se puede, también sin activarla
        $otro = $this->persona(Rol::ADMINISTRADOR);
        $this->actingAs($otro)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_el_propietario_cierra_la_sesion_de_todo_el_personal_menos_la_suya(): void
    {
        $administrador = $this->persona(Rol::ADMINISTRADOR);
        $trabajador = $this->persona(Rol::TRABAJADOR);
        $ajena = $this->persona(Rol::TRABAJADOR, Tenant::create(['nombre' => 'Finca Sur', 'codigo' => 'SUR']));
        $this->sesion($this->propietario, 'propia-en-otro-equipo');
        $this->sesion($administrador, 'admin-1');
        $this->sesion($trabajador, 'trab-1');
        $this->sesion($ajena, 'ajena-1');
        $trabajador->createToken('telefono');
        $recordar = $trabajador->getRememberToken();

        // Pide la contraseña
        $this->actingAs($this->propietario)->post(route('admin.ajustes.seguridad.cerrar-sesiones'), ['actual' => 'mala'])->assertSessionHasErrors('actual');
        $this->assertSame(4, DB::table('sessions')->count());

        $this->actingAs($this->propietario)->post(route('admin.ajustes.seguridad.cerrar-sesiones'), ['actual' => 'Clave-Segura-123'])
            ->assertSessionHas('estado', trans_choice('seguridad.personal.cerradas', 2, ['cantidad' => 2]));

        $this->assertEqualsCanonicalizing(['propia-en-otro-equipo', 'ajena-1'], DB::table('sessions')->pluck('id')->all());
        $this->assertSame(0, $trabajador->tokens()->count());
        $this->assertNotSame($recordar, $trabajador->fresh()->getRememberToken());
        $this->assertSame('Cerró la sesión de todo el personal (2 personas)', Bitacora::query()->where('accion', 'sesiones_cerradas')->sole()->textoDetalle());

        // Solo el propietario
        $this->actingAs($administrador)->post(route('admin.ajustes.seguridad.cerrar-sesiones'), ['actual' => 'Clave-Segura-123'])->assertForbidden();
    }

    public function test_ingresos_del_personal_filtrables_y_sin_los_de_otra_finca(): void
    {
        $trabajador = $this->persona(Rol::TRABAJADOR);
        $otraFinca = Tenant::create(['nombre' => 'Finca Sur', 'codigo' => 'SUR']);
        $ajena = $this->persona(Rol::TRABAJADOR, $otraFinca);
        $peticion = Request::create('/login', 'POST', server: ['REMOTE_ADDR' => '190.24.8.77', 'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0) Firefox/131.0']);
        RegistroIngresos::exitoso($trabajador, $peticion);
        RegistroIngresos::fallido($this->propietario, $peticion, Ingreso::MOTIVO_CONTRASENA);
        RegistroIngresos::exitoso($ajena, $peticion);
        $ajena->update(['name' => 'Persona de otra finca']);

        $this->actingAs($this->propietario)->get(route('admin.ajustes.seguridad.ingresos'))->assertOk()
            ->assertSee($trabajador->name)->assertSee('Firefox en Windows')->assertSee('190.24.8.x')->assertDontSee('190.24.8.77')
            ->assertSee(__('seguridad.ingresos.motivos.contrasena'))->assertDontSee('Persona de otra finca');
        $this->actingAs($this->propietario)->get(route('admin.ajustes.seguridad.ingresos', ['persona' => $trabajador->id]))->assertOk()
            ->assertDontSee(__('seguridad.ingresos.motivos.contrasena'));
        // Una persona de otra finca en el filtro no muestra nada suyo
        $this->actingAs($this->propietario)->get(route('admin.ajustes.seguridad.ingresos', ['persona' => $ajena->id]))->assertOk()->assertDontSee('Persona de otra finca');

        foreach ([Rol::ADMINISTRADOR, Rol::TRABAJADOR, Rol::CELADOR] as $rol) {
            $this->actingAs($this->persona($rol))->get(route('admin.ajustes.seguridad.ingresos'))->assertForbidden();
        }
    }
}
