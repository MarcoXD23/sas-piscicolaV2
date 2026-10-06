<?php

namespace Database\Seeders;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Datos de demostración para probar a mano (php artisan db:seed --class=DemoPiscicolaSeeder).
 * NUNCA corre por defecto ni en producción. Las contraseñas se generan al azar en cada ejecución
 * y se muestran una sola vez en la consola: no hay contraseñas fijas en el código.
 */
class DemoPiscicolaSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Los datos de demostración no se cargan en producción.');
        }

        $credenciales = DB::transaction(function () {
            $tenant = Tenant::create(['nombre' => 'Finca La Demostración', 'codigo' => 'demo-piscicola']);
            $credenciales = [];

            foreach ([
                ['Propietario Demo', 'propietario@demo.test', Rol::PROPIETARIO],
                ['Administrador Demo', 'admin@demo.test', Rol::ADMINISTRADOR],
            ] as [$nombre, $correo, $rol]) {
                $clave = Str::password(16);
                // tenant_id y aprobado_at no son asignables en masa a propósito
                $usuario = User::forceCreate([
                    'tenant_id' => $tenant->id,
                    'name' => $nombre,
                    'email' => $correo,
                    'password' => $clave,
                    'aprobado_at' => now(),
                ]);
                $usuario->asignarRoles([$rol]);
                $credenciales[] = [$rol, $correo, $clave];
            }

            app(TenantActual::class)->establecer($tenant->id);

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

            Lago::create(['nombre' => 'L03', 'tipo' => 'concreto']);

            return $credenciales;
        });

        $this->command?->warn('Datos de demostración creados. Guarda estas contraseñas: no se vuelven a mostrar.');
        $this->command?->table(['Rol', 'Correo', 'Contraseña'], $credenciales);
    }
}
