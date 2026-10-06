<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mediciones de calidad del agua por lago. Cada parámetro es opcional, pero se exige al menos uno.
     */
    public function up(): void
    {
        Schema::create('mediciones_agua', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('lago_id')->constrained('lagos')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('medido_at');
            $table->decimal('temperatura_c', 4, 1)->nullable();
            $table->decimal('oxigeno_mg_l', 5, 2)->nullable();
            $table->decimal('ph', 4, 2)->nullable();
            $table->decimal('amonio_mg_l', 6, 3)->nullable();
            $table->decimal('nitritos_mg_l', 6, 3)->nullable();
            $table->unsignedSmallInteger('transparencia_cm')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['lago_id', 'medido_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mediciones_agua');
    }
};
