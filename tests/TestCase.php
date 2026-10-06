<?php

namespace Tests;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Protección: las pruebas solo pueden correr contra PostgreSQL sas_piscicola_v2_test.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $conexion = $app['config']->get('database.default');
        $base = $app['config']->get("database.connections.{$conexion}.database");

        if ($conexion !== 'pgsql' || $base !== 'sas_piscicola_v2_test') {
            throw new RuntimeException(
                "Las pruebas deben correr sobre pgsql/sas_piscicola_v2_test (actual: {$conexion}/{$base}). ¿Hay config en caché? Ejecuta php artisan config:clear."
            );
        }

        return $app;
    }

    /** Crea un turno en curso (de 1 hora antes a 7 horas después de ahora) para el rol indicado. */
    protected function ponerEnTurno(User $usuario, string $rol): Turno
    {
        return Turno::create([
            'tenant_id' => $usuario->tenant_id,
            'user_id' => $usuario->id,
            'rol' => $rol,
            'inicia_at' => now()->subHour(),
            'termina_at' => now()->addHours(7),
        ]);
    }
}

