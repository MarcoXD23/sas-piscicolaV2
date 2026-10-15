<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajustes de operación de cada finca (clave → valor). Qué ajustes existen, su tipo y su valor por defecto están en
     * App\Support\Ajustes\CatalogoAjustes: aquí solo se guarda lo que la finca cambió. Un ajuste nuevo no necesita
     * migración. Tabla nueva: no toca datos existentes.
     */
    public function up(): void
    {
        Schema::create('ajustes_finca', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('clave', 80);
            $table->jsonb('valor');
            $table->foreignId('cambiado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_finca');
    }
};
