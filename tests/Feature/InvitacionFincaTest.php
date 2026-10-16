<?php

namespace Tests\Feature;

use App\Models\AceptacionLegal;
use App\Models\AdministradorPlataforma;
use App\Models\Fase;
use App\Models\InvitacionFinca;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Fincas\CreadorFinca;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invitaciones de finca: el administrador de plataforma invita un correo (una invitación por correo) y el cliente crea
 * su finca con el enlace (un solo uso, 7 días). El código de la finca lo genera la app. Un enlace que ya no sirve
 * muestra siempre el mismo aviso; con una sesión abierta no se crea nada.
 */
class InvitacionFincaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finca = Tenant::create(['nombre' => 'Finca Norte', 'codigo' => 'norte']);
        AdministradorPlataforma::create(['name' => 'Dueño', 'email' => 'dueno@software.test', 'password' => 'ClaveLarga12345']);
    }

    private function datosFinca(array $cambios = []): array
    {
        return $cambios + [
            'finca' => 'La Esperanza', 'pais' => 'co', 'zona_horaria' => 'America/Bogota',
            'nombre' => 'Cliente Nuevo', 'clave' => 'ClaveSegura123', 'clave_confirmation' => 'ClaveSegura123', 'acepto' => '1',
        ];
    }

    private function aviso(): string
    {
        return __('invitaciones.invalida');
    }

    private function fincasCreadas(): int
    {
        return Tenant::query()->where('nombre', 'La Esperanza')->count();
    }

    public function test_el_dueno_invita_un_correo_y_ve_el_enlace_una_sola_vez(): void
    {
        $this->entrarAPlataforma();
        $this->post(route('plataforma.invitaciones.store'), ['correo' => ' Cliente@Finca.test '])->assertSessionHas('invitacion_enlace');
        $enlace = session('invitacion_enlace');
        $invitacion = InvitacionFinca::sole();

        $this->assertSame('cliente@finca.test', $invitacion->correo);
        $this->assertSame(InvitacionFinca::PENDIENTE, $invitacion->estado());
        $this->assertTrue($invitacion->vence_at->equalTo(now()->addDays(7)));
        // Solo se guarda el sha256 del código del enlace
        $codigo = basename(parse_url($enlace, PHP_URL_PATH));
        $this->assertSame(InvitacionFinca::hash($codigo), $invitacion->codigo_hash);
        $this->assertStringNotContainsString($codigo, json_encode($invitacion->getAttributes()));

        // El enlace se ve en la página siguiente; después ya no
        $this->get(route('plataforma.fincas.index'))->assertSee($enlace, false)->assertSee('cliente@finca.test');
        $this->get(route('plataforma.fincas.index'))->assertDontSee($enlace, false)->assertSee(__('invitaciones.estados.pendiente'));
    }

    public function test_una_invitacion_por_correo(): void
    {
        $this->entrarAPlataforma();
        $invitar = fn (string $correo) => $this->post(route('plataforma.invitaciones.store'), ['correo' => $correo]);

        // Con una pendiente no se crea otra (sin importar mayúsculas ni espacios)
        $invitar('cliente@finca.test')->assertSessionHasNoErrors();
        $invitar('  CLIENTE@finca.test ')->assertSessionHasErrors(['correo' => __('invitaciones.correo_con_pendiente')]);
        $this->assertSame(1, InvitacionFinca::count());

        // Anulada sin usarse: sí se puede invitar de nuevo
        InvitacionFinca::sole()->forceFill(['anulada_at' => now()])->save();
        $invitar('cliente@finca.test')->assertSessionHasNoErrors();
        $this->assertSame(2, InvitacionFinca::count());

        // Vencida sin usarse: también
        InvitacionFinca::query()->latest('id')->first()->forceFill(['vence_at' => now()->subMinute()])->save();
        $invitar('Cliente@Finca.test')->assertSessionHasNoErrors();
        $this->assertSame(3, InvitacionFinca::count());

        // Ya usó la suya: no se invita de nuevo, aunque la usada sea vieja y no haya otra pendiente
        InvitacionFinca::query()->latest('id')->first()->forceFill(['usada_at' => now()])->save();
        $invitar('cliente@finca.test')->assertSessionHasErrors(['correo' => __('invitaciones.correo_ya_uso')]);

        // Ya tiene cuenta (en cualquier finca, sin importar mayúsculas): el mismo mensaje
        User::factory()->create(['tenant_id' => $this->finca->id, 'email' => 'ya@finca.test']);
        $invitar('YA@finca.test')->assertSessionHasErrors(['correo' => __('invitaciones.correo_ya_uso')]);
        $this->assertSame(3, InvitacionFinca::count());
    }

    public function test_ninguna_persona_de_una_finca_invita_ni_anula(): void
    {
        [$invitacion] = InvitacionFinca::crear('cliente@finca.test', null);

        foreach ([Rol::PROPIETARIO, Rol::ADMINISTRADOR, Rol::TRABAJADOR, Rol::CELADOR] as $rol) {
            $persona = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([$rol]);
            $this->actingAs($persona)->post(route('plataforma.invitaciones.store'), ['correo' => 'otro@finca.test'])->assertRedirect(route('plataforma.login'));
            $this->actingAs($persona)->post(route('plataforma.invitaciones.anular', $invitacion))->assertRedirect(route('plataforma.login'));
        }
        $this->assertSame(1, InvitacionFinca::count());
        $this->assertSame(InvitacionFinca::PENDIENTE, $invitacion->fresh()->estado());
    }

    public function test_el_cliente_crea_su_finca_con_el_enlace_y_entra_como_propietario(): void
    {
        [$invitacion, $codigo] = InvitacionFinca::crear('cliente@finca.test', null);

        // La invitación ya no pide el código de la finca: lo genera la app
        $this->get(route('invitacion.show', $codigo))->assertOk()->assertSee('cliente@finca.test')->assertSee(__('invitaciones.crear'))
            ->assertDontSee('name="codigo"', false);

        // El correo es el invitado, aunque se envíe otro; un código enviado a mano se ignora
        $this->post(route('invitacion.store', $codigo), $this->datosFinca(['correo' => 'intruso@finca.test', 'codigo' => 'mi-codigo']))
            ->assertRedirect(route('inicio'))->assertSessionHas('estado');

        $finca = Tenant::query()->where('nombre', 'La Esperanza')->sole();
        $this->assertSame(['CO', 'America/Bogota'], [$finca->pais, $finca->zona_horaria]);
        $this->assertMatchesRegularExpression('/^['.CreadorFinca::ALFABETO_CODIGO.']{6}$/', $finca->codigo);
        $propietario = TenantActual::sinFiltro(fn () => User::query()->where('tenant_id', $finca->id)->sole());
        $this->assertSame('cliente@finca.test', $propietario->email);
        $this->assertTrue($propietario->estaAprobado());
        $this->assertTrue($propietario->tieneRol(Rol::PROPIETARIO));
        $this->assertAuthenticatedAs($propietario, 'web');
        $this->assertTrue(TenantActual::sinFiltro(fn () => AceptacionLegal::query()->where('user_id', $propietario->id)->exists()));

        // Arranca como cualquier finca nueva: especies, fases y tabla de ración
        $this->assertSame(4, TenantActual::sinFiltro(fn () => Fase::query()->where('tenant_id', $finca->id)->count()));

        $invitacion->refresh();
        $this->assertSame([InvitacionFinca::USADA, $finca->id], [$invitacion->estado(), $invitacion->finca_creada_id]);

        // Un solo uso: el mismo enlace ya no sirve ni crea otra finca (con sesión o sin ella, el mismo aviso)
        $this->get(route('invitacion.show', $codigo))->assertOk()->assertSee($this->aviso());
        auth('web')->logout();
        $this->get(route('invitacion.show', $codigo))->assertOk()->assertSee($this->aviso());
        $this->post(route('invitacion.store', $codigo), $this->datosFinca(['finca' => 'Otra finca']))->assertOk()->assertSee($this->aviso());
        $this->assertSame(0, Tenant::query()->where('nombre', 'Otra finca')->count());
    }

    public function test_con_sesion_abierta_se_avisa_y_no_se_crea_nada(): void
    {
        [$invitacion, $codigo] = InvitacionFinca::crear('cliente@finca.test', null);
        $persona = tap(User::factory()->create(['tenant_id' => $this->finca->id, 'name' => 'Ana Celadora']))->asignarRoles([Rol::CELADOR]);

        // Antes la app mandaba callado al inicio de esa cuenta: ahora la página lo dice y ofrece cerrar sesión
        $this->actingAs($persona)->get(route('invitacion.show', $codigo))->assertOk()
            ->assertSee(__('invitaciones.sesion_abierta', ['nombre' => 'Ana Celadora']))
            ->assertSee(__('invitaciones.cerrar_y_continuar'))
            ->assertDontSee(__('invitaciones.crear'));

        // El POST con sesión abierta no crea nada y vuelve al aviso; la invitación no se gasta
        $this->actingAs($persona)->post(route('invitacion.store', $codigo), $this->datosFinca())->assertRedirect(route('invitacion.show', $codigo));
        $this->assertSame(0, $this->fincasCreadas());
        $this->assertSame(InvitacionFinca::PENDIENTE, $invitacion->fresh()->estado());
        $this->assertAuthenticatedAs($persona, 'web');

        // «Cerrar sesión y continuar»: cierra la sesión y vuelve al mismo enlace, que ya funciona
        $this->post(route('invitacion.salir', $codigo))->assertRedirect(route('invitacion.show', $codigo));
        $this->assertGuest('web');
        $this->get(route('invitacion.show', $codigo))->assertOk()->assertSee(__('invitaciones.crear'));
        $this->post(route('invitacion.store', $codigo), $this->datosFinca())->assertRedirect(route('inicio'));
        $this->assertSame(1, $this->fincasCreadas());

        // Un enlace inventado muestra el aviso de siempre, aunque haya sesión abierta
        $this->actingAs($persona)->get(route('invitacion.show', 'codigo-inventado'))->assertOk()->assertSee($this->aviso())
            ->assertDontSee(__('invitaciones.cerrar_y_continuar'));
    }

    public function test_vencida_anulada_o_inventada_muestran_el_mismo_aviso_y_no_crean_nada(): void
    {
        [$vencida, $codigoVencido] = InvitacionFinca::crear('vence@finca.test', null);
        [$anulada, $codigoAnulado] = InvitacionFinca::crear('anula@finca.test', null);
        $anulada->forceFill(['anulada_at' => now()])->save();
        $this->travel(InvitacionFinca::DIAS_VIGENCIA)->days();
        $this->travel(1)->minutes();
        $this->assertSame(InvitacionFinca::VENCIDA, $vencida->fresh()->estado());

        foreach ([$codigoVencido, $codigoAnulado, 'codigo-inventado-que-no-existe'] as $codigo) {
            $this->get(route('invitacion.show', $codigo))->assertOk()->assertSee($this->aviso())->assertDontSee(__('invitaciones.crear'));
            $this->post(route('invitacion.store', $codigo), $this->datosFinca())->assertOk()->assertSee($this->aviso());
        }
        $this->assertSame(0, $this->fincasCreadas());
    }

    public function test_el_dueno_anula_una_invitacion_pendiente(): void
    {
        [$invitacion, $codigo] = InvitacionFinca::crear('cliente@finca.test', null);
        $this->entrarAPlataforma();

        $this->post(route('plataforma.invitaciones.anular', $invitacion))->assertSessionHas('estado');
        $this->assertSame(InvitacionFinca::ANULADA, $invitacion->fresh()->estado());
        // Una ya anulada (o usada) no se vuelve a anular
        $this->post(route('plataforma.invitaciones.anular', $invitacion))->assertNotFound();

        $this->post(route('plataforma.logout'));
        $this->app['auth']->shouldUse('web');
        $this->get(route('invitacion.show', $codigo))->assertSee($this->aviso());
    }

    public function test_los_datos_se_validan_y_la_invitacion_sigue_pendiente(): void
    {
        [$invitacion, $codigo] = InvitacionFinca::crear('cliente@finca.test', null);

        $this->post(route('invitacion.store', $codigo), $this->datosFinca(['pais' => 'XX', 'clave' => 'corta', 'clave_confirmation' => 'corta', 'acepto' => null]))
            ->assertSessionHasErrors(['pais', 'clave', 'acepto']);
        // Contraseña débil: sin mayúscula, sin número o igual al correo
        foreach (['cachama2026', 'CachamaBlanca', 'cliente@finca.test'] as $debil) {
            $this->post(route('invitacion.store', $codigo), $this->datosFinca(['clave' => $debil, 'clave_confirmation' => $debil]))->assertSessionHasErrors('clave');
        }

        // Si mientras tanto alguien creó una cuenta con ese correo, no se crea otra
        User::factory()->create(['tenant_id' => $this->finca->id, 'email' => 'CLIENTE@finca.test']);
        $this->post(route('invitacion.store', $codigo), $this->datosFinca())->assertSessionHasErrors('correo');

        $this->assertSame(1, Tenant::count());
        $this->assertSame(InvitacionFinca::PENDIENTE, $invitacion->fresh()->estado());
    }

    public function test_la_finca_creada_no_ve_los_datos_de_otra_finca(): void
    {
        TenantActual::sinFiltro(fn () => tap(new Lago(['nombre' => 'Lago de la otra finca', 'tipo' => 'tierra']))->forceFill(['tenant_id' => $this->finca->id])->save());
        [, $codigo] = InvitacionFinca::crear('cliente@finca.test', null);

        $this->post(route('invitacion.store', $codigo), $this->datosFinca())->assertRedirect(route('inicio'));

        $this->get(route('admin.lagos.index'))->assertOk()->assertDontSee('Lago de la otra finca');
    }
}
