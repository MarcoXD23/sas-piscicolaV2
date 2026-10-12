<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unidad por defecto del peso total en el muestreo (g, kg o lb; validada en la aplicación con UnidadesPeso).
     * Gramos hasta que el propietario la cambie en Ajustes. Lo guardado sigue siempre en gramos.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('unidad_peso_muestreo', 2)->default('g');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('unidad_peso_muestreo');
        });
    }
};
