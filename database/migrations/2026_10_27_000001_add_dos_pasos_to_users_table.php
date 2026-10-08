<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verificación en dos pasos (TOTP) opcional por persona. Solo agrega columnas vacías: no cambia datos existentes.
 * - dos_pasos_clave: clave secreta CIFRADA con APP_KEY (cast «encrypted»).
 * - dos_pasos_ultimo_paso: último paso de tiempo aceptado, para que un código no se use dos veces.
 * - dos_pasos_recuperacion: hashes de los códigos de recuperación de un solo uso (nunca en texto plano).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('dos_pasos_clave')->nullable();
            $table->timestamp('dos_pasos_activado_at')->nullable();
            $table->unsignedBigInteger('dos_pasos_ultimo_paso')->nullable();
            $table->jsonb('dos_pasos_recuperacion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['dos_pasos_clave', 'dos_pasos_activado_at', 'dos_pasos_ultimo_paso', 'dos_pasos_recuperacion']);
        });
    }
};
