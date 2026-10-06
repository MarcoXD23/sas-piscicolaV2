<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Períodos en que el aireador de un lago estuvo encendido. apagado_at null = sigue encendido.
     */
    public function up(): void
    {
        Schema::create('aireaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('lago_id')->constrained('lagos')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('apagado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('encendido_at');
            $table->timestamp('apagado_at')->nullable();
            $table->string('motivo')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['lago_id', 'encendido_at']);
        });

        // Un lago no puede tener dos encendidos abiertos al mismo tiempo
        DB::statement('CREATE UNIQUE INDEX aireaciones_una_abierta_por_lago ON aireaciones (lago_id) WHERE apagado_at IS NULL');
        DB::statement('ALTER TABLE aireaciones ADD CONSTRAINT aireaciones_rango_valido CHECK (apagado_at IS NULL OR apagado_at >= encendido_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('aireaciones');
    }
};
