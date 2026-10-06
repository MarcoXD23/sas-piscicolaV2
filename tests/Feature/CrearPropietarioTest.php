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

    public function test_crea_finca_y_propietario_aprobado(): void
    {
        $this->artisan('piscicola:crear-propietario')
            ->expectsQuestion('Nombre de la finca', 'Finca de Prueba')
            ->expectsQuestion('Código de la finca (lo usará el personal para registrarse)', 'finca-prueba')
            ->expectsQuestion('Nombre del propietario', 'Propietario Prueba')
            ->expectsQuestion('Correo del propietario', 'propietario@example.test')
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'clave-segura-123')
            ->expectsQuestion('Confirma la contraseña', 'clave-segura-123')
            ->assertSuccessful();

        $tenant = Tenant::query()->where('codigo', 'finca-prueba')->firstOrFail();
        $usuario = User::query()->where('email', 'propietario@example.test')->firstOrFail();

        $this->assertSame($tenant->id, $usuario->tenant_id);
        $this->assertNotNull($usuario->aprobado_at);
        $this->assertTrue(Hash::check('clave-segura-123', $usuario->password));
        $this->assertSame([Rol::PROPIETARIO], $usuario->roles->pluck('nombre')->all());
    }

    public function test_falla_si_las_contrasenas_no_coinciden(): void
    {
        $this->artisan('piscicola:crear-propietario')
            ->expectsQuestion('Nombre de la finca', 'Finca de Prueba')
            ->expectsQuestion('Código de la finca (lo usará el personal para registrarse)', 'finca-prueba')
            ->expectsQuestion('Nombre del propietario', 'Propietario Prueba')
            ->expectsQuestion('Correo del propietario', 'propietario@example.test')
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'clave-segura-123')
            ->expectsQuestion('Confirma la contraseña', 'otra-clave-456')
            ->assertFailed();

        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('users', 0);
    }
}

