<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tratamientos sanitarios aplicados a un lago. Después de fecha_fin corre el período de retiro
     * (dias_retiro) durante el cual el pescado no se debe cosechar.
     */
    public function up(): void
    {
        Schema::create('tratamientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('lago_id')->constrained('lagos')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('motivo');
            $table->string('producto');
            $table->string('dosis');
            $table->string('via', 20); // baño, alimento o agua (validado en la aplicación)
            $table->unsignedSmallInteger('dias_retiro')->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['lago_id', 'fecha_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tratamientos');
    }
};
