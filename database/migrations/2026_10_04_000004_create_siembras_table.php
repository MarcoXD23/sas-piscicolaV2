<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siembras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('lago_id')->constrained('lagos')->restrictOnDelete();
            $table->string('especie');
            $table->date('fecha');
            
            // Valores iniciales
            $table->unsignedInteger('cantidad_alevinos');
            $table->decimal('peso_promedio_inicial_g', 8, 2);
            
            // Valores actuales (se actualizarán con mortalidad/muestreos/traslados)
            $table->unsignedInteger('peces_vivos');
            $table->decimal('peso_actual_g', 8, 2);
            $table->decimal('biomasa_kg', 12, 2);

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('lago_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siembras');
    }
};
