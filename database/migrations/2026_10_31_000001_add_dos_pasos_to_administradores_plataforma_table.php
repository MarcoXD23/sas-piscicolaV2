<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verificación en dos pasos (TOTP) obligatoria para el panel de plataforma. Solo agrega columnas vacías:
 * no cambia datos existentes. Mismas columnas que en users (ver 2026_10_27_000001_add_dos_pasos_to_users_table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('administradores_plataforma', function (Blueprint $table) {
            $table->text('dos_pasos_clave')->nullable();
            $table->timestamp('dos_pasos_activado_at')->nullable();
            $table->unsignedBigInteger('dos_pasos_ultimo_paso')->nullable();
            $table->jsonb('dos_pasos_recuperacion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('administradores_plataforma', function (Blueprint $table) {
            $table->dropColumn(['dos_pasos_clave', 'dos_pasos_activado_at', 'dos_pasos_ultimo_paso', 'dos_pasos_recuperacion']);
        });
    }
};
