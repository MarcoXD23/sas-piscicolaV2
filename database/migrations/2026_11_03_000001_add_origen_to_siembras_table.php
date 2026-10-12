<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Origen de cada lote (siembra): «siembra» (alevinos sembrados) o «traslado» (lote creado al trasladar peces desde
     * otro lago, que queda en lago_origen_id). Un traslado que suma peces a un lote existente no cambia su origen.
     * Relleno de lo existente: un lote es «traslado» si su PRIMER traslado recibido se creó en la misma transacción que él
     * (misma fecha, misma cantidad y mismo peso inicial que lo trasladado, y creado a lo sumo 60 segundos después).
     */
    public function up(): void
    {
        Schema::table('siembras', function (Blueprint $table) {
            $table->string('origen', 15)->default('siembra');   // siembra o traslado (validado en la aplicación)
            $table->foreignId('lago_origen_id')->nullable()->constrained('lagos')->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            UPDATE siembras s
            SET origen = 'traslado', lago_origen_id = t.lago_origen_id
            FROM (
                SELECT DISTINCT ON (siembra_destino_id) siembra_destino_id, lago_origen_id, fecha, cantidad, peso_promedio_g, created_at, tenant_id
                FROM traslados
                ORDER BY siembra_destino_id, id
            ) t
            WHERE t.siembra_destino_id = s.id
              AND t.tenant_id = s.tenant_id
              AND t.fecha = s.fecha
              AND t.cantidad = s.cantidad_alevinos
              AND t.peso_promedio_g = s.peso_promedio_inicial_g
              AND t.created_at >= s.created_at
              AND t.created_at <= s.created_at + interval '60 seconds'
        SQL);
    }

    public function down(): void
    {
        Schema::table('siembras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lago_origen_id');
            $table->dropColumn('origen');
        });
    }
};
