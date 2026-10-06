<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitácora de rondas de seguridad del celador (con o sin novedad).
     */
    public function up(): void
    {
        Schema::create('rondas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('turno_id')->nullable()->constrained('turnos')->nullOnDelete();
            $table->foreignId('lago_id')->nullable()->constrained('lagos')->restrictOnDelete();
            $table->timestamp('registrada_at');
            $table->string('tipo', 30); // ver Ronda::TIPOS (validado en la aplicación)
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'registrada_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rondas');
    }
};
