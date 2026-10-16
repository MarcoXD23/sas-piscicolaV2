<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Fincas\CreadorFinca;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrearPropietarioTest extends TestCase
{
    use RefreshDatabase;

    /** El código de la finca ya no se pregunta: lo genera la app y el comando lo muestra al final. */
    private function comando(string $pais, string $zona, string $confirmacion = 'ClaveSegura123', string $clave = 'ClaveSegura123')
    {
        return $this->artisan('piscicola:crear-propietario')
            ->expectsQuestion(__('comandos.crear_propietario.finca'), 'Finca de Prueba')
            ->expectsQuestion(__('comandos.crear_propietario.pais'), $pais)
            ->expectsQuestion(__('comandos.crear_propietario.zona'), $zona)
            ->expectsQuestion(__('comandos.crear_propietario.nombre'), 'Propietario Prueba')
            ->expectsQuestion(__('comandos.crear_propietario.correo'), 'propietario@example.test')
            ->expectsQuestion(__('comandos.crear_propietario.clave'), $clave)
            ->expectsQuestion(__('comandos.crear_propietario.confirmacion'), $confirmacion);
    }

    public function test_crea_finca_con_su_region_y_propietario_aprobado(): void
    {
        $this->comando('mx', 'America/Mexico_City')
            ->expectsOutputToContain('Código de la finca:')
            ->assertSuccessful();

        $tenant = Tenant::query()->where('nombre', 'Finca de Prueba')->firstOrFail();
        $usuario = TenantActual::sinFiltro(fn () => User::query()->where('email', 'propietario@example.test')->firstOrFail());

        $this->assertMatchesRegularExpression('/^['.CreadorFinca::ALFABETO_CODIGO.']{6}$/', $tenant->codigo);
        $this->assertSame('MX', $tenant->pais);
        $this->assertSame('America/Mexico_City', $tenant->zona_horaria);
        $this->assertSame('es', $tenant->idioma);
        $this->assertSame($tenant->id, $usuario->tenant_id);
        $this->assertNotNull($usuario->aprobado_at);
        $this->assertTrue(Hash::check('ClaveSegura123', $usuario->password));
        $this->assertSame([Rol::PROPIETARIO], $usuario->roles->pluck('nombre')->all());
    }

    public function test_falla_si_las_contrasenas_no_coinciden(): void
    {
        $this->comando('CO', 'America/Bogota', 'OtraClave456')->assertFailed();

        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_falla_con_una_contrasena_debil(): void
    {
        $this->comando('CO', 'America/Bogota', 'cachama2026', 'cachama2026')->assertFailed();

        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_falla_con_pais_o_zona_invalidos(): void
    {
        $this->comando('XX', 'America/Bogota')->assertFailed();
        $this->comando('CO', 'Marte/Base')->assertFailed();

        $this->assertDatabaseCount('tenants', 0);
    }
}
