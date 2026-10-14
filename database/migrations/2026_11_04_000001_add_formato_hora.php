<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Formato de hora (12 o 24, validado en la aplicación con App\Support\Hora): el de cada persona y el de la finca.
     * Vacío = el siguiente en la cadena (persona → finca → país → 24). Solo cambia cómo se muestra la hora.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('formato_hora', 2)->nullable();
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('formato_hora', 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('formato_hora');
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('formato_hora');
        });
    }
};
