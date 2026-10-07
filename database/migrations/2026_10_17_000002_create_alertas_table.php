<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de alertas por finca. Una alerta abierta (cerrada_at nulo) no se repite:
 * el índice único parcial impide dos abiertas del mismo tipo para la misma referencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 20);          // stock, mortalidad, agua (validado en la aplicación)
            $table->string('referencia', 100);   // concentrado o id del lago
            $table->string('severidad', 15);     // advertencia, critica
            $table->jsonb('datos');
            $table->timestamp('abierta_at');
            $table->timestamp('cerrada_at')->nullable();
            $table->timestamp('notificada_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'abierta_at']);
        });

        DB::statement('CREATE UNIQUE INDEX alertas_abierta_unica ON alertas (tenant_id, tipo, referencia) WHERE cerrada_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas');
    }
};
