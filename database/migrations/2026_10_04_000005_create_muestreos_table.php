<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('muestreos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('siembra_id')->constrained('siembras')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            
            $table->date('fecha');
            $table->unsignedInteger('peces_pesados');
            $table->decimal('peso_total_g', 8, 2);
            $table->decimal('peso_promedio_g', 8, 2);
            
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('siembra_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muestreos');
    }
};
