<?php

namespace App\Support\Datos;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Tablas con datos de una finca (las que tienen tenant_id), leídas de la base para no desactualizarse
 * cuando se agregan tablas, y el orden en que se pueden borrar según sus claves foráneas reales.
 */
final class TablasFinca
{
    /** @return list<string> */
    public static function todas(): array
    {
        return collect(DB::select(
            "SELECT table_name FROM information_schema.columns WHERE table_schema = 'public' AND column_name = 'tenant_id' ORDER BY table_name"
        ))->pluck('table_name')->all();
    }

    /**
     * Orden de borrado: cada tabla va antes que las tablas a las que apunta (de hijas a madres).
     *
     * @return list<string>
     */
    public static function ordenDeBorrado(): array
    {
        $tablas = self::todas();
        $dependeDe = array_fill_keys($tablas, []);

        foreach (self::clavesForaneas() as $fk) {
            if (isset($dependeDe[$fk->hija], $dependeDe[$fk->madre]) && $fk->hija !== $fk->madre) {
                $dependeDe[$fk->hija][] = $fk->madre;
            }
        }

        // Una tabla se puede borrar cuando ninguna otra pendiente la referencia
        $orden = [];
        $pendientes = $tablas;
        while ($pendientes !== []) {
            $libres = array_values(array_filter($pendientes, fn (string $tabla) => ! collect($pendientes)
                ->contains(fn (string $otra) => $otra !== $tabla && in_array($tabla, $dependeDe[$otra], true))));
            if ($libres === []) {
                throw new RuntimeException('Hay claves foráneas en círculo entre: '.implode(', ', $pendientes));
            }
            sort($libres);
            $orden = [...$orden, ...$libres];
            $pendientes = array_values(array_diff($pendientes, $libres));
        }

        return $orden;
    }

    /**
     * Tablas SIN tenant_id que apuntan a una tabla de finca (deben borrarse aparte o no existir).
     *
     * @return list<string>
     */
    public static function dependientesSinTenant(): array
    {
        $tablas = self::todas();

        return collect(self::clavesForaneas())
            ->filter(fn ($fk) => in_array($fk->madre, $tablas, true) && ! in_array($fk->hija, $tablas, true))
            ->pluck('hija')->unique()->sort()->values()->all();
    }

    /** @return list<object{hija: string, madre: string}> */
    private static function clavesForaneas(): array
    {
        return DB::select(<<<'SQL'
            SELECT hija.relname AS hija, madre.relname AS madre
            FROM pg_constraint c
            JOIN pg_class hija ON hija.oid = c.conrelid
            JOIN pg_class madre ON madre.oid = c.confrelid
            JOIN pg_namespace n ON n.oid = hija.relnamespace
            WHERE c.contype = 'f' AND n.nspname = 'public'
            SQL);
    }
}
