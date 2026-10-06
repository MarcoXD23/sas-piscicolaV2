<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\User;
use Database\Seeders\DemoPiscicolaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_la_finca_demo_con_contrasenas_aleatorias(): void
    {
        $this->seed(DemoPiscicolaSeeder::class);

        $propietario = User::withoutGlobalScopes()->where('email', 'propietario@demo.test')->sole();
        $this->assertNotNull($propietario->tenant_id);
        $this->assertNotNull($propietario->aprobado_at);
        $this->assertTrue($propietario->tieneRol('propietario'));
        // Ninguna contraseña fija conocida
        $this->assertFalse(Hash::check('12345678', $propietario->password));
        $this->assertSame(3, Lago::withoutGlobalScopes()->count());

        $this->assertStringNotContainsString("'12345678'", file_get_contents(database_path('seeders/DemoPiscicolaSeeder.php')));
    }

    public function test_no_corre_en_produccion(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->expectException(RuntimeException::class);
            (new DemoPiscicolaSeeder)->run();
        } finally {
            $this->app['env'] = 'testing';
        }
    }
}
