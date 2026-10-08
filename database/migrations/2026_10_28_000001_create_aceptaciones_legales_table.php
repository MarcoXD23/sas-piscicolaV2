<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién aceptó los términos de uso y la política de privacidad, qué versión y cuándo (UTC). No se guarda la IP.
 * Solo crea la tabla: no cambia datos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aceptaciones_legales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('version', 20);
            $table->timestamp('aceptado_at');
            $table->timestamps();

            $table->unique(['user_id', 'version']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aceptaciones_legales');
    }
};
