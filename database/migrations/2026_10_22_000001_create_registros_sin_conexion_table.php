<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registros hechos sin conexión que ya se recibieron: el id que generó el teléfono no se procesa dos veces
 * aunque el envío se repita (por ejemplo, si se cortó la señal antes de recibir la respuesta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_sin_conexion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid');
            $table->string('ruta', 255);
            $table->timestamp('registrado_at');   // hora real en el teléfono (UTC)
            $table->timestamps();

            $table->unique(['tenant_id', 'uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_sin_conexion');
    }
};
