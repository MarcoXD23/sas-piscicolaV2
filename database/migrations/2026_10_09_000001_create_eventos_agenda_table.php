<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agenda operativa del propietario: cosechas, compras, visitas, mantenimiento, pagos, etc.
     */
    public function up(): void
    {
        Schema::create('eventos_agenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('lago_id')->nullable()->constrained('lagos')->restrictOnDelete();
            $table->string('titulo');
            $table->string('tipo', 20); // ver EventoAgenda::TIPOS (validado en la aplicación)
            $table->timestamp('inicia_at');
            $table->timestamp('termina_at')->nullable();
            $table->boolean('todo_el_dia')->default(false);
            $table->unsignedSmallInteger('recordar_dias_antes')->default(0);
            $table->text('descripcion')->nullable();
            $table->timestamp('completado_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'inicia_at']);
        });

        DB::statement('ALTER TABLE eventos_agenda ADD CONSTRAINT eventos_agenda_rango_valido CHECK (termina_at IS NULL OR termina_at >= inicia_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_agenda');
    }
};
