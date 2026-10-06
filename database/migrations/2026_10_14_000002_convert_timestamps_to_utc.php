<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Zona en la que se guardaban las fechas y horas antes de pasar a UTC. */
    private const ZONA_ANTERIOR = 'America/Bogota';

    /**
     * La aplicación pasa a guardar fechas y horas en UTC. Convierte todas las columnas
     * "timestamp without time zone" existentes desde la hora de Bogotá. Las columnas de solo fecha
     * (tipo date) no cambian: son días del calendario de la finca.
     */
    public function up(): void
    {
        $this->convertir(self::ZONA_ANTERIOR, 'UTC');
    }

    public function down(): void
    {
        $this->convertir('UTC', self::ZONA_ANTERIOR);
    }

    private function convertir(string $desde, string $hacia): void
    {
        $columnas = DB::select("
            SELECT table_name, column_name
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND data_type = 'timestamp without time zone'
            ORDER BY table_name, column_name
        ");

        foreach ($columnas as $columna) {
            $tabla = DB::getQueryGrammar()->wrapTable($columna->table_name);
            $campo = DB::getQueryGrammar()->wrap($columna->column_name);

            DB::update("UPDATE {$tabla} SET {$campo} = ({$campo} AT TIME ZONE ?) AT TIME ZONE ? WHERE {$campo} IS NOT NULL", [$desde, $hacia]);
        }
    }
};
