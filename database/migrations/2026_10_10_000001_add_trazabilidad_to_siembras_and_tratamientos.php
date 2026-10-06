<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos de trazabilidad para el libro de campo: origen de los alevinos y registro ICA del producto.
     * Son opcionales para no invalidar los registros existentes.
     */
    public function up(): void
    {
        Schema::table('siembras', function (Blueprint $table) {
            $table->string('proveedor_alevinos')->nullable()->after('especie');
            $table->string('lote_alevinos')->nullable()->after('proveedor_alevinos');
        });

        Schema::table('tratamientos', function (Blueprint $table) {
            $table->string('registro_ica')->nullable()->after('producto');
        });
    }

    public function down(): void
    {
        Schema::table('tratamientos', function (Blueprint $table) {
            $table->dropColumn('registro_ica');
        });

        Schema::table('siembras', function (Blueprint $table) {
            $table->dropColumn(['proveedor_alevinos', 'lote_alevinos']);
        });
    }
};
