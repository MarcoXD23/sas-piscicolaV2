<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de traslados de peces entre lagos (un desdoble es un traslado parcial).
     */
    public function up(): void
    {
        Schema::create('traslados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('siembra_origen_id')->constrained('siembras')->restrictOnDelete();
            $table->foreignId('siembra_destino_id')->constrained('siembras')->restrictOnDelete();
            $table->foreignId('lago_origen_id')->constrained('lagos')->restrictOnDelete();
            $table->foreignId('lago_destino_id')->constrained('lagos')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha');
            $table->unsignedInteger('cantidad');
            $table->decimal('peso_promedio_g', 8, 2);
            $table->decimal('biomasa_kg', 12, 2);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('lago_origen_id');
            $table->index('lago_destino_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traslados');
    }
};
