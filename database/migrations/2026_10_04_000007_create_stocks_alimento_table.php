<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks_alimento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('tipo_concentrado'); // Ej: "Mojarra 32%"
            $table->decimal('cantidad_kg', 12, 2)->default(0);
            
            $table->timestamps();

            $table->index('tenant_id');
            // Un solo registro de stock por tipo de concentrado por finca
            $table->unique(['tenant_id', 'tipo_concentrado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks_alimento');
    }
};
