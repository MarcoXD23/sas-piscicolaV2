<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('nombre');
            $table->string('tipo'); // tierra, geomembrana, concreto
            $table->timestamps();

            $table->index('tenant_id');
            // Un nombre no debería repetirse en la misma finca
            $table->unique(['tenant_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lagos');
    }
};
