<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_alimento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('lago_id')->nullable()->constrained('lagos')->restrictOnDelete();
            
            $table->date('fecha');
            $table->string('tipo_movimiento'); // 'entrada' o 'salida'
            $table->string('tipo_concentrado');
            $table->decimal('cantidad_kg', 12, 2);
            
            // Campos opcionales (principalmente para entradas)
            $table->string('proveedor')->nullable();
            $table->string('lote')->nullable();
            $table->unsignedInteger('bultos')->nullable();
            $table->decimal('peso_bulto_kg', 8, 2)->nullable();
            
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_alimento');
    }
};
