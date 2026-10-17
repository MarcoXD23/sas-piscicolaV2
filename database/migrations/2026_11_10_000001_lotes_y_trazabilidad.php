<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F5: Lotes y trazabilidad. Todas las columnas aceptan vacío: lo que ya existe sigue funcionando igual.
     * - terceros.provee: qué provee el tercero (alevinos, alimento, otros), uno o varios, como lista JSON de códigos
     *   validados en la aplicación (Tercero::PROVEE); sin enum.
     * - siembras.proveedor_id: proveedor del catálogo (terceros). El texto viejo de proveedor_alevinos no se borra: se
     *   sigue leyendo y se liga con la pantalla de revisión, sin adivinar.
     * - siembras.numero_lote: número de lote de la finca (manual o generado por la app, según el ajuste de la finca).
     *   No es único: el lote aparte que nace de un traslado conserva el número del lote de origen.
     * - lagos.fase_desde: desde cuándo el lago está en su fase, para los días en la fase.
     * - traslados.modo: «sumar» (a un lote existente) o «aparte» (lote nuevo); los traslados viejos quedan vacíos.
     */
    public function up(): void
    {
        Schema::table('terceros', function (Blueprint $table) {
            $table->jsonb('provee')->nullable();
        });

        Schema::table('siembras', function (Blueprint $table) {
            $table->foreignId('proveedor_id')->nullable()->constrained('terceros')->restrictOnDelete();
            $table->string('numero_lote', 40)->nullable();

            $table->index(['tenant_id', 'proveedor_id']);
            $table->index(['tenant_id', 'numero_lote']);
        });

        Schema::table('lagos', function (Blueprint $table) {
            $table->date('fase_desde')->nullable();
        });

        Schema::table('traslados', function (Blueprint $table) {
            $table->string('modo', 10)->nullable();   // sumar o aparte (validado en la aplicación)
        });
    }

    public function down(): void
    {
        Schema::table('traslados', function (Blueprint $table) {
            $table->dropColumn('modo');
        });

        Schema::table('lagos', function (Blueprint $table) {
            $table->dropColumn('fase_desde');
        });

        Schema::table('siembras', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'numero_lote']);
            $table->dropIndex(['tenant_id', 'proveedor_id']);
            $table->dropConstrainedForeignId('proveedor_id');
            $table->dropColumn('numero_lote');
        });

        Schema::table('terceros', function (Blueprint $table) {
            $table->dropColumn('provee');
        });
    }
};
