<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Fincas\CreadorFinca;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acceso más seguro: una sola regla de contraseña nueva (Password::defaults: 10 caracteres, mayúscula, minúscula y
 * número) y el código de la finca generado por la app, que se busca sin importar mayúsculas ni espacios.
 */
class AccesoSeguroTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finca = Tenant::create(['nombre' => 'Finca Norte', 'codigo' => 'K7M4PQ']);
    }

    private function registrar(string $codigo, string $clave, string $correo = 'nueva@finca.test')
    {
        return $this->post(route('registro'), [
            'codigo_finca' => $codigo, 'name' => 'Persona Nueva', 'email' => $correo,
            'password' => $clave, 'password_confirmation' => $clave, 'acepto' => '1',
        ]);
    }

    public function test_la_contrasena_nueva_pide_10_caracteres_con_mayuscula_minuscula_y_numero(): void
    {
        foreach (['12345678', 'cachama2026', 'CACHAMA2026', 'Cachama26', 'CachamaBlanca'] as $debil) {
            $this->registrar('K7M4PQ', $debil)->assertSessionHasErrors('password');
        }
        $this->assertSame(0, TenantActual::sinFiltro(fn () => User::count()));

        // El símbolo es opcional
        $this->registrar('K7M4PQ', 'Cachama2026')->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $this->assertSame(1, TenantActual::sinFiltro(fn () => User::count()));

        // Los mensajes van en español y el formulario muestra la lista de requisitos
        $this->registrar('K7M4PQ', 'cachama2026', 'otra@finca.test')->assertSessionHasErrors(['password' => __('validation.password.mixed', ['attribute' => 'contraseña'])]);
        $this->get(route('registro'))->assertSee('data-requisitos-de=', false)->assertSee(__('acceso.requisitos.largo', ['minimo' => 10]));
    }

    public function test_quien_tiene_una_contrasena_vieja_de_8_caracteres_sigue_entrando(): void
    {
        $usuario = tap(User::factory()->create(['tenant_id' => $this->finca->id, 'password' => 'clave123']))->asignarRoles([Rol::TRABAJADOR]);

        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'clave123'])->assertRedirect(route('inicio'));
        $this->assertAuthenticatedAs($usuario, 'web');
    }

    public function test_el_codigo_generado_tiene_6_caracteres_sin_confusiones_y_no_se_repite(): void
    {
        $codigos = collect(range(1, 40))->map(fn () => CreadorFinca::generarCodigo());
        foreach ($codigos as $codigo) {
            $this->assertMatchesRegularExpression('/^['.CreadorFinca::ALFABETO_CODIGO.']{6}$/', $codigo);
            $this->assertDoesNotMatchRegularExpression('/[0O1IL]/', $codigo);
        }

        // Dos fincas nunca reciben el mismo: si choca (sin importar mayúsculas), se genera otro
        $una = CreadorFinca::crear(['finca' => 'Una', 'pais' => 'CO', 'zona_horaria' => 'America/Bogota', 'nombre' => 'Una', 'correo' => 'una@finca.test', 'clave' => 'ClaveSegura123']);
        $otra = CreadorFinca::crear(['finca' => 'Otra', 'pais' => 'CO', 'zona_horaria' => 'America/Bogota', 'nombre' => 'Otra', 'correo' => 'otra@finca.test', 'clave' => 'ClaveSegura123']);
        $this->assertNotSame($una->tenant->codigo, $otra->tenant->codigo);
        $this->assertSame(1, Tenant::query()->where('codigo', $una->tenant->codigo)->count());
    }

    public function test_registro_encuentra_la_finca_en_minusculas_o_con_espacios(): void
    {
        $this->registrar('  k7m4pq ', 'Cachama2026')->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $usuario = TenantActual::sinFiltro(fn () => User::query()->where('email', 'nueva@finca.test')->sole());
        $this->assertSame($this->finca->id, $usuario->tenant_id);
    }

    public function test_una_finca_vieja_con_codigo_propio_sigue_funcionando(): void
    {
        $vieja = Tenant::create(['nombre' => 'La Esperanza', 'codigo' => 'la-esperanza']);

        $this->registrar('LA-ESPERANZA ', 'Cachama2026')->assertSessionHasNoErrors();
        $this->assertSame($vieja->id, TenantActual::sinFiltro(fn () => User::query()->where('email', 'nueva@finca.test')->value('tenant_id')));
        $this->assertSame('la-esperanza', $vieja->fresh()->codigo);   // no se cambia ni se migra
        $this->assertTrue($vieja->tieneCodigo(' La-Esperanza'));
        $this->assertFalse($vieja->tieneCodigo('la-esperanz'));
    }

    public function test_dos_fincas_viejas_con_el_mismo_codigo_en_distintas_mayusculas_no_se_adivinan(): void
    {
        Tenant::create(['nombre' => 'A', 'codigo' => 'F01']);
        Tenant::create(['nombre' => 'B', 'codigo' => 'f01']);

        $this->assertNull(Tenant::porCodigo('f01'));
        $this->registrar('F01', 'Cachama2026')->assertSessionHasErrors('registro');
    }

    public function test_el_propietario_ve_su_codigo_con_copiar(): void
    {
        $propietario = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::PROPIETARIO]);

        $this->actingAs($propietario)->get(route('admin.datos.index'))->assertOk()
            ->assertSee('value="K7M4PQ"', false)->assertSee(__('datos.codigo.copiar'))->assertSee(__('datos.codigo.ayuda'));
    }
}
