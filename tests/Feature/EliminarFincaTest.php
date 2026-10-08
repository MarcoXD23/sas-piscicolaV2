<?php

namespace Tests\Feature;

use App\Models\AdministradorPlataforma;
use App\Models\Bitacora;
use App\Models\Especie;
use App\Models\EspecieFoto;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\FincaEnEliminacion;
use App\Support\Datos\EliminadorFinca;
use App\Support\Datos\TablasFinca;
use App\Support\TenantActual;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Eliminar una finca: confirmación obligatoria, periodo de gracia con cancelación, y borrado definitivo de todo lo
 * de esa finca (también su bitácora de solo inserción y sus archivos) sin tocar nada de otra.
 */
class EliminarFincaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private Tenant $otra;

    private User $propietario;

    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('fotos');
        Storage::fake('exportaciones');
        Storage::fake('local');
        Notification::fake();

        $this->otra = $this->fincaConDatos('Otra', 'F02');
        $this->finca = $this->fincaConDatos('Finca Norte', 'NORTE');
        app(TenantActual::class)->establecer($this->finca->id);
        $this->propietario = User::query()->whereHas('roles', fn ($q) => $q->where('nombre', Rol::PROPIETARIO))->sole();
        $this->trabajador = User::query()->whereHas('roles', fn ($q) => $q->where('nombre', Rol::TRABAJADOR))->sole();
    }

    /** Finca con personal, lago, siembra, bitácora, foto y token. */
    private function fincaConDatos(string $nombre, string $codigo): Tenant
    {
        $finca = Tenant::create(['nombre' => $nombre, 'codigo' => $codigo]);
        app(TenantActual::class)->establecer($finca->id);
        $dueno = User::factory()->conRoles(Rol::PROPIETARIO)->create(['tenant_id' => $finca->id, 'password' => 'clave-segura-123']);
        $trabajador = User::factory()->conRoles(Rol::TRABAJADOR)->create(['tenant_id' => $finca->id, 'password' => 'clave-segura-123']);
        $trabajador->createToken('celular');
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $lago->id, 'especie_id' => Especie::deClave('mojarra roja')->id, 'fecha' => '2026-09-01', 'cantidad_alevinos' => 10,
            'peso_promedio_inicial_g' => 5, 'peces_vivos' => 10, 'peso_actual_g' => 5, 'biomasa_kg' => 0.05]);
        Bitacora::registrar($dueno, 'roles', $lago, [Rol::PROPIETARIO], ['persona' => 'x', 'antes' => 'a', 'despues' => 'b']);
        Storage::disk('fotos')->put($finca->id.'/1/foto.jpg', 'x');
        EspecieFoto::create(['especie_id' => Especie::deClave('mojarra roja')->id, 'user_id' => $dueno->id, 'ruta_original' => $finca->id.'/1/foto.jpg',
            'ruta_ligera' => $finca->id.'/1/foto.webp', 'tipo_original' => 'image/jpeg', 'bytes_original' => 1, 'bytes_ligera' => 1, 'ancho' => 1, 'alto' => 1, 'orden' => 1]);

        return $finca;
    }

    private function solicitar(array $datos = [])
    {
        return $this->actingAs($this->propietario)->post(route('admin.datos.eliminar'), $datos + ['codigo' => 'NORTE', 'actual' => 'clave-segura-123']);
    }

    /** El plazo de gracia vence. Con la hora de la base: la función borrar_bitacora_finca la usa, y las pruebas congelan la hora de PHP. */
    private function vencerPlazo(): void
    {
        DB::table('tenants')->where('id', $this->finca->id)->update(['eliminacion_programada_at' => DB::raw("(now() AT TIME ZONE 'UTC') - interval '1 minute'")]);
    }

    public function test_la_confirmacion_es_obligatoria(): void
    {
        $this->solicitar(['codigo' => 'norte'])->assertInvalid(['codigo' => 'El código no coincide']);
        $this->solicitar(['actual' => 'otra'])->assertInvalid(['actual']);
        $this->assertFalse($this->finca->fresh()->enEliminacion());

        $trabajador = $this->trabajador;
        $this->actingAs($trabajador)->post(route('admin.datos.eliminar'), ['codigo' => 'NORTE', 'actual' => 'clave-segura-123'])->assertForbidden();
    }

    public function test_al_pedirla_queda_suspendida_con_periodo_de_gracia_y_se_avisa(): void
    {
        $this->solicitar()->assertRedirect(route('admin.datos.eliminacion'));

        $finca = $this->finca->fresh();
        $this->assertTrue($finca->enEliminacion());
        $this->assertTrue($finca->estaSuspendida());
        $this->assertSame(14, (int) round($finca->eliminacion_solicitada_at->diffInDays($finca->eliminacion_programada_at)));
        $this->assertSame(0, $this->trabajador->tokens()->count());                         // accesos cortados
        $this->assertSame(1, TenantActual::sinFiltro(fn () => DB::table('personal_access_tokens')->count()));   // los de la otra finca siguen
        Notification::assertSentTo($this->propietario, FincaEnEliminacion::class);
        $this->assertSame(1, Bitacora::where('accion', 'eliminacion')->count());

        // Durante el plazo: el personal no entra; el propietario solo ve la pantalla para cancelar
        auth()->logout();
        $this->post(route('login'), ['email' => $this->trabajador->email, 'password' => 'clave-segura-123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post(route('login'), ['email' => $this->propietario->email, 'password' => 'clave-segura-123'])->assertRedirect(route('inicio'));
        $this->get(route('inicio'))->assertRedirect(route('admin.datos.eliminacion'));
        $this->get(route('admin.lagos.index'))->assertRedirect(route('admin.datos.eliminacion'));
        $this->get(route('admin.datos.eliminacion'))->assertOk()->assertSee(__('datos.eliminar.cancelar_boton'));
        // La API no entra (ni el propietario)
        $this->postJson('/api/login', ['email' => $this->propietario->email, 'password' => 'clave-segura-123'])->assertUnprocessable();
    }

    public function test_se_puede_cancelar_en_el_plazo(): void
    {
        $this->solicitar();

        $this->actingAs($this->propietario)->post(route('admin.datos.cancelar'), ['actual' => 'mala'])->assertInvalid(['actual']);
        $this->actingAs($this->propietario)->post(route('admin.datos.cancelar'), ['actual' => 'clave-segura-123'])->assertRedirect(route('inicio'));

        $finca = $this->finca->fresh();
        $this->assertFalse($finca->enEliminacion());
        $this->assertFalse($finca->estaSuspendida());
        $this->actingAs($this->trabajador)->get(route('inicio'))->assertOk();
        $this->artisan('piscicola:eliminar-fincas')->assertSuccessful();
        $this->assertNotNull(Tenant::find($this->finca->id));
    }

    public function test_el_administrador_de_plataforma_la_pide_y_la_cancela(): void
    {
        AdministradorPlataforma::create(['name' => 'Dueño', 'email' => 'dueno@software.test', 'password' => 'ClaveLarga12345']);
        $this->post(route('plataforma.login'), ['email' => 'dueno@software.test', 'password' => 'ClaveLarga12345']);

        $this->post(route('plataforma.fincas.eliminar', $this->finca), ['codigo' => 'NORTE', 'actual' => 'mala'])->assertSessionHasErrors('actual');
        $this->post(route('plataforma.fincas.eliminar', $this->finca), ['codigo' => 'NORTE', 'actual' => 'ClaveLarga12345'])->assertSessionHasNoErrors();
        $this->assertTrue($this->finca->fresh()->enEliminacion());
        $this->get(route('plataforma.fincas.index'))->assertSee(__('datos.eliminar.cancelar_plataforma'));

        $this->post(route('plataforma.fincas.cancelar-eliminacion', $this->finca))->assertSessionHasNoErrors();
        $this->assertFalse($this->finca->fresh()->enEliminacion());
        $this->assertFalse($this->finca->fresh()->estaSuspendida());
    }

    public function test_antes_de_vencer_el_plazo_no_se_borra_nada(): void
    {
        $this->solicitar();

        $this->artisan('piscicola:eliminar-fincas')->assertSuccessful();
        $this->assertNotNull(Tenant::find($this->finca->id));
        // Ni la función de la base deja borrar su bitácora
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::select('SELECT borrar_bitacora_finca(?)', [$this->finca->id]));
    }

    public function test_el_borrado_definitivo_elimina_todo_lo_de_la_finca_y_nada_de_otra(): void
    {
        $this->assertSame([], TablasFinca::dependientesSinTenant());   // nada sin tenant_id cuelga de una tabla de finca
        $otraAntes = collect(TablasFinca::todas())->mapWithKeys(fn ($t) => [$t => DB::table($t)->where('tenant_id', $this->otra->id)->count()])->all();
        $usuariosFinca = DB::table('users')->where('tenant_id', $this->finca->id)->pluck('email');

        $this->solicitar();
        $this->vencerPlazo();
        $this->artisan('piscicola:eliminar-fincas')->assertSuccessful();

        // Nada de la finca en ninguna tabla, ni sus personas, ni sus archivos
        $this->assertNull(DB::table('tenants')->where('id', $this->finca->id)->first());
        foreach (TablasFinca::todas() as $tabla) {
            $this->assertSame(0, DB::table($tabla)->where('tenant_id', $this->finca->id)->count(), $tabla);
        }
        $this->assertSame(0, DB::table('users')->whereIn('email', $usuariosFinca)->count());
        $this->assertSame([], Storage::disk('fotos')->allFiles($this->finca->id));

        // La otra finca, intacta (también su bitácora y sus fotos)
        $this->assertSame($otraAntes, collect(TablasFinca::todas())->mapWithKeys(fn ($t) => [$t => DB::table($t)->where('tenant_id', $this->otra->id)->count()])->all());
        $this->assertSame(1, DB::table('bitacora')->where('tenant_id', $this->otra->id)->count());
        Storage::disk('fotos')->assertExists($this->otra->id.'/1/foto.jpg');
        $this->assertSame(1, DB::table('personal_access_tokens')->count());
    }

    public function test_la_bitacora_de_otra_finca_sigue_sin_poder_borrarse(): void
    {
        $this->solicitar();
        $this->vencerPlazo();
        $this->artisan('piscicola:eliminar-fincas')->assertSuccessful();

        foreach ([
            fn () => DB::table('bitacora')->where('tenant_id', $this->otra->id)->delete(),
            fn () => DB::select('SELECT borrar_bitacora_finca(?)', [$this->otra->id]),
            fn () => DB::statement('TRUNCATE bitacora'),
        ] as $intento) {
            try {
                DB::transaction($intento);
                $this->fail('Se pudo borrar bitácora de otra finca');
            } catch (QueryException) {
            }
        }
        $this->assertSame(1, DB::table('bitacora')->where('tenant_id', $this->otra->id)->count());
    }

    public function test_si_se_restaura_una_copia_vieja_la_finca_se_vuelve_a_borrar(): void
    {
        $fila = (array) DB::table('tenants')->where('id', $this->finca->id)->first();
        $this->solicitar();
        $this->vencerPlazo();
        $this->artisan('piscicola:eliminar-fincas')->assertSuccessful();
        $this->assertContains($this->finca->id, EliminadorFinca::eliminadas());

        // Se «restaura» la finca (sin la eliminación pedida, como estaba antes)
        DB::table('tenants')->insert($fila);
        $this->artisan('piscicola:eliminar-fincas')->expectsOutputToContain('reapareció')->assertSuccessful();
        $this->assertNull(DB::table('tenants')->where('id', $this->finca->id)->first());
    }
}
