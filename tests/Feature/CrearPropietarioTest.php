<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrearPropietarioTest extends TestCase
{
    use RefreshDatabase;

    private function comando(string $pais, string $zona, string $confirmacion = 'clave-segura-123')
    {
        return $this->artisan('piscicola:crear-propietario')
            ->expectsQuestion(__('comandos.crear_propietario.finca'), 'Finca de Prueba')
            ->expectsQuestion(__('comandos.crear_propietario.codigo'), 'finca-prueba')
            ->expectsQuestion(__('comandos.crear_propietario.pais'), $pais)
            ->expectsQuestion(__('comandos.crear_propietario.zona'), $zona)
            ->expectsQuestion(__('comandos.crear_propietario.nombre'), 'Propietario Prueba')
            ->expectsQuestion(__('comandos.crear_propietario.correo'), 'propietario@example.test')
            ->expectsQuestion(__('comandos.crear_propietario.clave'), 'clave-segura-123')
            ->expectsQuestion(__('comandos.crear_propietario.confirmacion'), $confirmacion);
    }

    public function test_crea_finca_con_su_region_y_propietario_aprobado(): void
    {
        $this->comando('mx', 'America/Mexico_City')->assertSuccessful();

        $tenant = Tenant::query()->where('codigo', 'finca-prueba')->firstOrFail();
        $usuario = User::query()->where('email', 'propietario@example.test')->firstOrFail();

        $this->assertSame('MX', $tenant->pais);
        $this->assertSame('America/Mexico_City', $tenant->zona_horaria);
        $this->assertSame('es', $tenant->idioma);
        $this->assertSame($tenant->id, $usuario->tenant_id);
        $this->assertNotNull($usuario->aprobado_at);
        $this->assertTrue(Hash::check('clave-segura-123', $usuario->password));
        $this->assertSame([Rol::PROPIETARIO], $usuario->roles->pluck('nombre')->all());
    }

    public function test_falla_si_las_contrasenas_no_coinciden(): void
    {
        $this->comando('CO', 'America/Bogota', 'otra-clave-456')->assertFailed();

        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_falla_con_pais_o_zona_invalidos(): void
    {
        $this->comando('XX', 'America/Bogota')->assertFailed();
        $this->comando('CO', 'Marte/Base')->assertFailed();

        $this->assertDatabaseCount('tenants', 0);
    }
}
