<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pesajes por canasta de una cosecha (registro histórico de cada canasta pesada).
     */
    public function up(): void
    {
        Schema::create('pesajes_cosecha', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('cosecha_id')->constrained('cosechas')->restrictOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->decimal('bruto_kg', 8, 2);
            $table->decimal('tara_kg', 8, 2);
            $table->decimal('neto_kg', 8, 2);
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['cosecha_id', 'numero']);
        });

        DB::statement('ALTER TABLE pesajes_cosecha ADD CONSTRAINT pesajes_cosecha_valores_validos CHECK (tara_kg >= 0 AND bruto_kg > tara_kg AND neto_kg = bruto_kg - tara_kg)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pesajes_cosecha');
    }
};
