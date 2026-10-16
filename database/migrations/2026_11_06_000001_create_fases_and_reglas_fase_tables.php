<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Fases iniciales (copia fija de Fase::POR_DEFECTO al crear esta migración). */
    private const FASES = ['alevinaje' => 1, 'precria' => 2, 'levante' => 3, 'engorde' => 4];

    /** Reglas iniciales: solo los datos de la finca piloto (diseño, tema 15.1); copia fija de Fase::reglaPorDefecto(). */
    private const ESPECIES_CON_REGLAS = ['mojarra roja', 'mojarra negra', 'cachama blanca', 'bocachico', 'bagre rayado'];

    private const REGLAS = [
        'precria' => ['peso_salida_g' => 30.0, 'dias_esperados' => 60],
        'levante' => ['peso_entrada_g' => 30.0, 'peso_salida_g' => 150.0, 'dias_esperados' => 90],
        'engorde' => ['peso_entrada_g' => 150.0, 'dias_esperados' => 90, 'densidad_min_m2' => 15.0, 'densidad_max_m2' => 24.0],
    ];

    private const SALIDA_ENGORDE = ['cachama blanca' => 850.0, 'mojarra roja' => 600.0, 'mojarra negra' => 600.0];

    /**
     * F2: Fases de los lagos (alevinaje, precría, levante, engorde…), reglas por fase y especie, y área del lago.
     * - fases: lista de fases por finca, en su orden.
     * - reglas_fase: peso de entrada y salida, días, supervivencia esperada y densidad de referencia por fase y especie.
     * - lagos.fase_id y lagos.area_m2: opcionales; los lagos que ya existen quedan «sin fase» hasta que alguien se la ponga.
     * Los datos iniciales se cargan con DB::table (sin modelos), para que la migración no cambie si cambia el código.
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
            $table->foreignId('especie_id')->constrained('especies')->cascadeOnDelete();
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

        $ahora = now();
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $especies = DB::table('especies')->where('tenant_id', $tenantId)->whereIn('clave', self::ESPECIES_CON_REGLAS)->pluck('clave', 'id');

            foreach (self::FASES as $clave => $orden) {
                $faseId = DB::table('fases')->insertGetId([
                    'tenant_id' => $tenantId, 'clave' => $clave, 'nombre' => __('fases.por_defecto.'.$clave), 'orden' => $orden,
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);

                if (! isset(self::REGLAS[$clave])) {
                    continue;
                }
                foreach ($especies as $especieId => $claveEspecie) {
                    $salida = $clave === 'engorde' && isset(self::SALIDA_ENGORDE[$claveEspecie]) ? ['peso_salida_g' => self::SALIDA_ENGORDE[$claveEspecie]] : [];
                    DB::table('reglas_fase')->insert(self::REGLAS[$clave] + $salida + [
                        'tenant_id' => $tenantId, 'fase_id' => $faseId, 'especie_id' => $especieId,
                        'created_at' => $ahora, 'updated_at' => $ahora,
                    ]);
                }
            }
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
