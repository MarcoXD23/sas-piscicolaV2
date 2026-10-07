<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Umbrales de alerta que cada finca puede editar en Ajustes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedSmallInteger('alerta_stock_dias')->default(7);
            $table->decimal('alerta_mortalidad_porcentaje', 5, 2)->default(3);
            $table->unsignedSmallInteger('alerta_mortalidad_dias')->default(7);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['alerta_stock_dias', 'alerta_mortalidad_porcentaje', 'alerta_mortalidad_dias']);
        });
    }
};
