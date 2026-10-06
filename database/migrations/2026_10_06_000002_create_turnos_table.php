<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turnos de trabajo de trabajadores y celadores. Un turno puede cruzar la medianoche
     * (por eso se guardan fecha y hora completas). Los turnos pasados quedan como historial.
     */
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('rol', 20); // trabajador o celador (validado en la aplicación)
            $table->timestamp('inicia_at');
            $table->timestamp('termina_at');
            $table->foreignId('asignado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['user_id', 'inicia_at', 'termina_at']);
            $table->index(['tenant_id', 'inicia_at']);
        });

        DB::statement('ALTER TABLE turnos ADD CONSTRAINT turnos_rango_valido CHECK (termina_at > inicia_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
