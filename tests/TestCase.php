<?php

namespace Tests;

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
}

