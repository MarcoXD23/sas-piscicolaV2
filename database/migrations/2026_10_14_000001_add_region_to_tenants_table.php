<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Región de cada finca: país (ISO 3166-1 alfa-2), zona horaria e idioma.
     * Se validan en la aplicación (sin enum de base de datos).
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('pais', 2)->nullable()->after('codigo');
            $table->string('zona_horaria', 64)->default('America/Bogota')->after('pais');
            $table->string('idioma', 5)->default('es')->after('zona_horaria');
        });

        // Las fincas que ya existen se crearon cuando la aplicación era solo para Colombia
        DB::table('tenants')->whereNull('pais')->update(['pais' => 'CO']);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['pais', 'zona_horaria', 'idioma']);
        });
    }
};
