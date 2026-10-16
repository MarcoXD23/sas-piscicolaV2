<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invitaciones para crear una finca: el administrador de plataforma invita un correo y el cliente crea su finca con
     * el enlace (un solo uso, vence en 7 días). Es una tabla de la plataforma, como administradores_plataforma: no lleva
     * tenant_id (la finca todavía no existe). finca_creada_id queda vacío si después se borra la finca.
     * El código del enlace se guarda cifrado (sha256): quien vea la base no puede usarlo.
     */
    public function up(): void
    {
        Schema::create('invitaciones_finca', function (Blueprint $table) {
            $table->id();
            $table->string('correo');
            $table->string('codigo_hash', 64)->unique();
            $table->timestamp('vence_at');
            $table->timestamp('usada_at')->nullable();
            $table->timestamp('anulada_at')->nullable();
            $table->foreignId('creada_por')->nullable()->constrained('administradores_plataforma')->nullOnDelete();
            $table->foreignId('finca_creada_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->timestamps();

            $table->index('correo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitaciones_finca');
    }
};
