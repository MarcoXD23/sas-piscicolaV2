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
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invitaciones de finca: el administrador de plataforma invita un correo y el cliente crea su finca con el enlace
 * (un solo uso, 7 días). Un enlace que ya no sirve muestra siempre el mismo aviso.
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
            'finca' => 'La Esperanza', 'codigo' => 'la-esperanza', 'pais' => 'co', 'zona_horaria' => 'America/Bogota',
            'nombre' => 'Cliente Nuevo', 'clave' => 'ClaveSegura123', 'clave_confirmation' => 'ClaveSegura123', 'acepto' => '1',
        ];
    }

    private function aviso(): string
    {
        return __('invitaciones.invalida');
    }

    public function test_el_dueno_invita_un_correo_y_ve_el_enlace_una_sola_vez(): void
    {

        $this->entrarAPlataforma();
        $this->post(route('plataforma.invitaciones.store'), ['correo' => 'Cliente@Finca.test'])->assertSessionHas('invitacion_enlace');
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

        // Un correo que ya tiene cuenta (en cualquier finca, sin importar mayúsculas) no se invita
        User::factory()->create(['tenant_id' => $this->finca->id, 'email' => 'ya@finca.test']);
        $this->post(route('plataforma.invitaciones.store'), ['correo' => 'YA@finca.test'])->assertSessionHasErrors('correo');
        $this->assertSame(1, InvitacionFinca::count());
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

        $this->get(route('invitacion.show', $codigo))->assertOk()->assertSee('cliente@finca.test')->assertSee(__('invitaciones.crear'));

        // El correo es el invitado, aunque se envíe otro
        $this->post(route('invitacion.store', $codigo), $this->datosFinca(['correo' => 'intruso@finca.test']))
            ->assertRedirect(route('inicio'))->assertSessionHas('estado');

        $finca = Tenant::query()->where('codigo', 'la-esperanza')->sole();
        $this->assertSame(['La Esperanza', 'CO', 'America/Bogota'], [$finca->nombre, $finca->pais, $finca->zona_horaria]);
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

        // Un solo uso: el mismo enlace ya no sirve ni crea otra finca
        auth('web')->logout();
        $this->get(route('invitacion.show', $codigo))->assertOk()->assertSee($this->aviso());
        $this->post(route('invitacion.store', $codigo), $this->datosFinca(['codigo' => 'otra-finca']))->assertOk()->assertSee($this->aviso());
        $this->assertSame(0, Tenant::query()->where('codigo', 'otra-finca')->count());
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
        $this->assertSame(0, Tenant::query()->where('codigo', 'la-esperanza')->count());
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

        $this->post(route('invitacion.store', $codigo), $this->datosFinca(['codigo' => 'norte']))->assertSessionHasErrors(['codigo' => __('invitaciones.codigo_usado')]);
        $this->post(route('invitacion.store', $codigo), $this->datosFinca(['codigo' => 'con espacios', 'pais' => 'XX', 'clave' => 'corta', 'clave_confirmation' => 'corta', 'acepto' => null]))
            ->assertSessionHasErrors(['codigo', 'pais', 'clave', 'acepto']);
        $this->post(route('invitacion.store', $codigo), $this->datosFinca(['clave' => 'cliente@finca.test', 'clave_confirmation' => 'cliente@finca.test']))
            ->assertSessionHasErrors('clave');

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
