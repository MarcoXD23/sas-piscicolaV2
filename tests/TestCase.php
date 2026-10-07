<?php

namespace Tests;

use App\Models\Especie;
use App\Models\Turno;
use App\Models\User;
use App\Support\Region;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
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

    protected function setUp(): void
    {
        parent::setUp();

        // Reloj fijo por defecto: mediodía en la zona por defecto de las fincas. Así "hoy" es el mismo día
        // en UTC y en la finca, y las pruebas no dependen de la hora en que se corren. Cada prueba puede
        // fijar otra hora con travelTo().
        $this->travelTo(Carbon::parse('2026-10-15 12:00', Region::ZONA_POR_DEFECTO));
        Region::olvidar();
    }

    /** Crea un turno en curso (de 1 hora antes a 7 horas después de ahora) para el rol indicado. */
    /** Id de una especie por defecto ('mojarra roja', 'cachama blanca'...) en el catálogo de la finca activa. */
    protected function especie(string $clave): int
    {
        return Especie::deClave($clave)->id;
    }

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
