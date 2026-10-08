<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asistente de primer uso de una finca nueva (App\Support\PrimerUso): marcas de los pasos que no se pueden deducir
 * de los datos (revisar Ajustes, revisar especies, definir pesos) y si la lista se ocultó o se completó.
 * Las fincas que ya tienen al menos un lago ya están operando: su lista queda completada y no se les muestra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('ajustes_revisados_at')->nullable();
            $table->timestamp('especies_revisadas_at')->nullable();
            $table->timestamp('pesos_definidos_at')->nullable();
            $table->timestamp('primer_uso_oculto_at')->nullable();
            $table->timestamp('primer_uso_completado_at')->nullable();
        });

        DB::statement('UPDATE tenants SET primer_uso_completado_at = NOW() WHERE EXISTS (SELECT 1 FROM lagos WHERE lagos.tenant_id = tenants.id)');
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['ajustes_revisados_at', 'especies_revisadas_at', 'pesos_definidos_at', 'primer_uso_oculto_at', 'primer_uso_completado_at']);
        });
    }
};
