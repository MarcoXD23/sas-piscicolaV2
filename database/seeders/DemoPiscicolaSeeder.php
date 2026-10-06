<?php

namespace Database\Seeders;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder para rellenar datos de demostración a mano (php artisan db:seed --class=DemoPiscicolaSeeder).
 * NUNCA corre por defecto.
 */
class DemoPiscicolaSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $tenant = Tenant::create([
                'nombre' => 'Finca La Demostración',
                'codigo' => 'demo-piscicola',
            ]);

            $propietario = User::create([
                'tenant_id' => $tenant->id,
                'name' => 'Propietario Demo',
                'email' => 'propietario@demo.test',
                'password' => Hash::make('12345678'),
                'aprobado_at' => now(),
            ]);
            $propietario->asignarRoles([Rol::PROPIETARIO]);

            $admin = User::create([
                'tenant_id' => $tenant->id,
                'name' => 'Admin Demo',
                'email' => 'admin@demo.test',
                'password' => Hash::make('12345678'),
                'aprobado_at' => now(),
            ]);
            $admin->asignarRoles([Rol::ADMINISTRADOR]);

            app(\App\Support\TenantActual::class)->establecer($tenant->id);

            $lago1 = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
            Siembra::create([
                'lago_id' => $lago1->id,
                'especie' => 'mojarra roja',
                'fecha' => now()->subDays(45),
                'cantidad_alevinos' => 10000,
                'peso_promedio_inicial_g' => 5.0,
                'peces_vivos' => 9800,
                'peso_actual_g' => 85.5,
                'biomasa_kg' => (9800 * 85.5) / 1000,
            ]);

            $lago2 = Lago::create(['nombre' => 'L02', 'tipo' => 'geomembrana']);
            Siembra::create([
                'lago_id' => $lago2->id,
                'especie' => 'cachama blanca',
                'fecha' => now()->subDays(10),
                'cantidad_alevinos' => 5000,
                'peso_promedio_inicial_g' => 3.5,
                'peces_vivos' => 4950,
                'peso_actual_g' => 12.0,
                'biomasa_kg' => (4950 * 12.0) / 1000,
            ]);

            Lago::create(['nombre' => 'L03 - Vacío', 'tipo' => 'concreto']);
        });
    }
}
