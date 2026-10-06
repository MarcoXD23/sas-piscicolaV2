<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tareas asignadas al personal de campo, permisos de ausencia y horas extra.
     * Permisos y horas extra pasan por revisión del propietario (estado: pendiente, aprobado, rechazado).
     */
    public function up(): void
    {
        Schema::create('tareas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('asignada_a')->constrained('users')->restrictOnDelete();
            $table->foreignId('creada_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('lago_id')->nullable()->constrained('lagos')->restrictOnDelete();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->date('fecha');
            $table->timestamp('completada_at')->nullable();
            $table->text('nota_cierre')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['asignada_a', 'completada_at']);
        });

        Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('motivo');
            $table->string('estado', 20)->default('pendiente'); // pendiente, aprobado o rechazado
            $table->foreignId('revisado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revisado_at')->nullable();
            $table->string('respuesta')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['user_id', 'fecha_inicio']);
            $table->index(['tenant_id', 'estado']);
        });

        Schema::create('horas_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha');
            $table->decimal('horas', 4, 2);
            $table->string('motivo');
            $table->string('estado', 20)->default('pendiente'); // pendiente, aprobado o rechazado
            $table->foreignId('revisado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revisado_at')->nullable();
            $table->string('respuesta')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['user_id', 'fecha']);
            $table->index(['tenant_id', 'estado']);
        });

        DB::statement('ALTER TABLE permisos ADD CONSTRAINT permisos_rango_valido CHECK (fecha_fin >= fecha_inicio)');
        DB::statement('ALTER TABLE horas_extras ADD CONSTRAINT horas_extras_rango_valido CHECK (horas > 0 AND horas <= 12)');
    }

    public function down(): void
    {
        Schema::dropIfExists('horas_extras');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('tareas');
    }
};
