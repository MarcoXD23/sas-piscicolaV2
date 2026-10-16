<?php

use App\Models\Fase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F2: Fases de los lagos (alevinaje, precría, levante, engorde…), reglas por fase y especie, y área del lago.
     * - fases: lista de fases por finca, en su orden.
     * - reglas_fase: peso de entrada y salida, días, supervivencia esperada y densidad de referencia por fase y especie.
     * - lagos.fase_id y lagos.area_m2: opcionales; los lagos que ya existen quedan «sin fase» hasta que alguien se la ponga.
     */
    public function up(): void
    {
        Schema::create('fases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('clave', 40)->nullable();
            $table->string('nombre', 80);
            $table->unsignedSmallInteger('orden')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'nombre']);
            $table->index(['tenant_id', 'orden']);
        });

        Schema::create('reglas_fase', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('fase_id')->constrained('fases')->cascadeOnDelete();
            $table->unsignedBigInteger('especie_id')->index();
            $table->decimal('peso_entrada_g', 8, 2)->nullable();
            $table->decimal('peso_salida_g', 8, 2)->nullable();
            $table->unsignedSmallInteger('dias_esperados')->nullable();
            $table->decimal('supervivencia_porcentaje', 5, 2)->nullable();
            $table->decimal('densidad_min_m2', 7, 2)->nullable();
            $table->decimal('densidad_max_m2', 7, 2)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'fase_id', 'especie_id']);
        });

        Schema::table('lagos', function (Blueprint $table) {
            $table->foreignId('fase_id')->nullable()->constrained('fases')->nullOnDelete();
            $table->decimal('area_m2', 10, 2)->nullable();
        });

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            Fase::cargarPorDefecto((int) $tenantId);
        }
    }

    public function down(): void
    {
        Schema::table('lagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fase_id');
            $table->dropColumn('area_m2');
        });

        Schema::dropIfExists('reglas_fase');
        Schema::dropIfExists('fases');
    }
};

