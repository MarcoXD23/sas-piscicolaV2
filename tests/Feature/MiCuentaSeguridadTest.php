<?php

namespace Tests\Feature;

use App\Models\Ingreso;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Seguridad\Equipo;
use App\Support\Seguridad\RegistroIngresos;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mi cuenta y seguridad: mi nombre, mi rol y mi finca, mis sesiones abiertas, mis últimos ingresos (exitosos y fallidos,
 * sin la IP completa) y el aviso de ingreso desde un equipo nuevo.
 */
class MiCuentaSeguridadTest extends TestCase
{
    use RefreshDatabase;

    private const ANDROID = 'Mozilla/5.0 (Linux; Android 14; SM-A145M) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36';

    private const WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36 Edg/130.0';

    private Tenant $finca;

    private User $persona;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finca = Tenant::create(['nombre' => 'Finca Norte', 'codigo' => 'NORTE']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->persona = tap(User::factory()->create(['tenant_id' => $this->finca->id, 'email' => 'ana@finca.test', 'name' => 'Ana', 'password' => 'Clave-Segura-123']))
            ->asignarRoles([Rol::TRABAJADOR]);
    }

    private function entrar(string $clave = 'Clave-Segura-123', string $agente = self::ANDROID, ?string $equipo = null)
    {
        // Cada ingreso es un equipo aparte: sin las cabeceras ni las cookies de la petición anterior
        $this->flushHeaders();
        $this->defaultCookies = [];
        auth('web')->logout();

        $this->withHeaders(['User-Agent' => $agente])->withServerVariables(['REMOTE_ADDR' => '190.24.8.77']);
        if ($equipo !== null) {
            $this->withCookie(RegistroIngresos::COOKIE, $equipo);
        }

        return $this->post(route('login'), ['email' => 'ana@finca.test', 'password' => $clave]);
    }

    public function test_el_equipo_y_la_red_sin_la_ip_completa(): void
    {
        $this->assertSame('Chrome en Android', Equipo::describir(self::ANDROID));
        $this->assertSame('Edge en Windows', Equipo::describir(self::WINDOWS));
        $this->assertSame('Safari en iPhone', Equipo::describir('Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Version/17.5 Mobile/15E148 Safari/604.1'));
        $this->assertSame('Equipo desconocido', Equipo::describir('okhttp/4.12'));
        $this->assertSame('190.24.8.x', Equipo::red('190.24.8.77'));
        $this->assertSame('2800:484:a2c::/48', Equipo::red('2800:484:a2c:9f00::1'));
        $this->assertNull(Equipo::red('no es ip'));
    }

    public function test_mis_datos_nombre_rol_y_finca(): void
    {
        $this->actingAs($this->persona)->get(route('mi-cuenta.index'))->assertOk()
            ->assertSee('Finca Norte')->assertSee('Trabajador')->assertSee('ana@finca.test')->assertSee(__('seguridad.mi_cuenta.correo_ayuda'));

        $this->actingAs($this->persona)->put(route('mi-cuenta.nombre'), ['name' => '  Ana María  '])->assertSessionHas('estado', __('seguridad.mi_cuenta.nombre_guardado'));
        $this->assertSame('Ana María', $this->persona->fresh()->name);
        $this->actingAs($this->persona)->put(route('mi-cuenta.nombre'), ['name' => ''])->assertSessionHasErrors('name');
        // El correo no se cambia desde aquí
        $this->actingAs($this->persona)->put(route('mi-cuenta.nombre'), ['name' => 'Ana', 'email' => 'otra@finca.test']);
        $this->assertSame('ana@finca.test', $this->persona->fresh()->email);
    }

    public function test_cada_ingreso_queda_registrado_exitoso_o_fallido(): void
    {
        $this->entrar('mala')->assertSessionHasErrors('email');
        $this->entrar()->assertRedirect(route('inicio'));
        // Un correo que no existe no se guarda
        $this->flushHeaders();
        auth('web')->logout();
        $this->post(route('login'), ['email' => 'nadie@finca.test', 'password' => 'x']);

        $ingresos = Ingreso::query()->orderBy('id')->get();
        $this->assertCount(2, $ingresos);
        $this->assertSame([false, true], $ingresos->pluck('exitoso')->all());
        $this->assertSame(Ingreso::MOTIVO_CONTRASENA, $ingresos[0]->motivo);
        $this->assertSame(['Chrome en Android', '190.24.8.x', Ingreso::CANAL_WEB], [$ingresos[1]->equipo, $ingresos[1]->red, $ingresos[1]->canal]);
        $this->assertSame($this->finca->id, $ingresos[1]->tenant_id);
        $this->assertStringNotContainsString('190.24.8.77', json_encode(DB::table('ingresos')->get()));

        $this->actingAs($this->persona)->get(route('mi-cuenta.index'))->assertOk()
            ->assertSee(__('seguridad.ingresos.titulo'))->assertSee('Chrome en Android')->assertSee('190.24.8.x')
            ->assertSee(__('seguridad.ingresos.motivos.contrasena'));
    }

    public function test_aviso_de_ingreso_desde_un_equipo_nuevo(): void
    {
        // Primer ingreso: no es «nuevo» (no había otro equipo) y deja la cookie del equipo
        $primero = $this->entrar();
        $equipo = $primero->getCookie(RegistroIngresos::COOKIE)?->getValue();
        $this->assertNotNull($equipo);
        $this->assertFalse(Ingreso::query()->latest('id')->first()->equipo_nuevo);

        // Mismo equipo (misma cookie): no avisa
        $this->entrar(equipo: $equipo);
        $this->assertFalse(Ingreso::query()->latest('id')->first()->equipo_nuevo);

        // Otro equipo (sin la cookie): avisa en el inicio
        $this->entrar(agente: self::WINDOWS)->assertRedirect(route('inicio'));
        $nuevo = Ingreso::query()->latest('id')->first();
        $this->assertTrue($nuevo->equipo_nuevo);
        $this->actingAs($this->persona)->get(route('inicio'))->assertOk()
            ->assertSee(__('seguridad.aviso_equipo_nuevo.titulo'))->assertSee('Edge en Windows')->assertSee(__('seguridad.aviso_equipo_nuevo.revisar'));

        // «Entendido» lo cierra; solo la propia persona puede cerrarlo
        $otra = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::TRABAJADOR]);
        $this->actingAs($otra)->post(route('mi-cuenta.ingresos.visto', $nuevo->id));
        $this->assertNull($nuevo->fresh()->aviso_cerrado_at);
        $this->actingAs($this->persona)->post(route('mi-cuenta.ingresos.visto', $nuevo->id))->assertRedirect();
        $this->assertNotNull($nuevo->fresh()->aviso_cerrado_at);
        $this->actingAs($this->persona)->get(route('inicio'))->assertDontSee(__('seguridad.aviso_equipo_nuevo.titulo'));
    }

    public function test_mis_sesiones_cerrar_una_y_cerrar_todas_las_demas(): void
    {
        $otra = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::TRABAJADOR]);
        $sesion = fn (User $u, string $id, string $agente) => DB::table('sessions')->insert(['id' => $id, 'user_id' => $u->id, 'ip_address' => '190.24.8.77',
            'user_agent' => $agente, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
        $sesion($this->persona, 'telefono-ana', self::ANDROID);
        $sesion($this->persona, 'pc-ana', self::WINDOWS);
        $sesion($otra, 'de-otra-persona', self::ANDROID);
        $this->persona->createToken('app');

        $this->actingAs($this->persona)->get(route('mi-cuenta.index'))->assertOk()
            ->assertSee(__('seguridad.sesiones.titulo'))->assertSee('Chrome en Android')->assertSee('Edge en Windows')->assertDontSee('190.24.8.77');

        // Cerrar una: solo las propias
        $this->actingAs($this->persona)->delete(route('mi-cuenta.sesiones.cerrar', 'de-otra-persona'))->assertNotFound();
        $this->actingAs($this->persona)->delete(route('mi-cuenta.sesiones.cerrar', 'telefono-ana'))->assertSessionHas('estado', __('seguridad.sesiones.cerrada'));
        $this->assertEqualsCanonicalizing(['pc-ana', 'de-otra-persona'], DB::table('sessions')->pluck('id')->all());

        // Cerrar todas las demás: pide la contraseña y revoca los tokens de la API
        $this->actingAs($this->persona)->post(route('mi-cuenta.sesiones.cerrar-demas'), ['actual' => 'mala'])->assertSessionHasErrors('actual');
        $this->assertSame(2, DB::table('sessions')->count());
        $this->actingAs($this->persona)->post(route('mi-cuenta.sesiones.cerrar-demas'), ['actual' => 'Clave-Segura-123'])->assertSessionHas('estado');
        $this->assertSame(['de-otra-persona'], DB::table('sessions')->pluck('id')->all());
        $this->assertSame(0, $this->persona->tokens()->count());
    }

    public function test_mis_ingresos_no_muestran_los_de_otra_persona_ni_los_de_otra_finca(): void
    {
        $companera = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::TRABAJADOR]);
        $otraFinca = Tenant::create(['nombre' => 'Finca Sur', 'codigo' => 'SUR']);
        $ajena = tap(User::factory()->create(['tenant_id' => $otraFinca->id]))->asignarRoles([Rol::TRABAJADOR]);
        $peticion = \Illuminate\Http\Request::create('/login', 'POST', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/131.0']);
        RegistroIngresos::exitoso($companera, $peticion);
        RegistroIngresos::exitoso($ajena, $peticion);

        // Cada ingreso queda en la finca de su persona, y la finca activa no ve los de otra
        $this->assertSame($otraFinca->id, TenantActual::sinFiltro(fn () => Ingreso::query()->where('user_id', $ajena->id)->value('tenant_id')));
        $this->assertSame([$companera->id], Ingreso::query()->pluck('user_id')->all());
        $this->actingAs($this->persona)->get(route('mi-cuenta.index'))->assertOk()->assertDontSee('Firefox en Linux')->assertSee(__('seguridad.ingresos.vacio'));
    }
}
