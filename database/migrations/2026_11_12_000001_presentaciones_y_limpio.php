<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F6b: Presentaciones, limpio y rendimiento (docs/diseno/dia-de-proceso.md, temas 6, 12 y 15.5). Solo agrega.
 * - tamanos: tamaños de cada especie con el nombre de la finca y un rango de peso solo informativo.
 * - procesos: procesos de la finca. presentaciones: especie + tamaño + proceso, con color de cabuya en texto libre.
 *   Ninguna se borra si ya se usó en un pesaje: se desactiva.
 * - pesajes_limpio: cada pesada del limpio de la jornada por presentación (las cuentas de CalculoPesaje).
 * - reproducciones_jornada: kg de «reproducción» por lago y especie (solo con el ajuste encendido). Esos peces vuelven al
 *   lago: se restan del entero antes del reparto y del rendimiento; las siembras no se tocan.
 * - jornada_rendimientos: rendimiento por jornada y especie al cerrar, con la referencia usada, el aviso y el motivo.
 * - especies.rendimiento_referencia: referencia inicial de la finca (%), hasta que haya promedio real.
 * - cosechas: limpio_kg (parte del limpio de la jornada), peces_esperados y peso_esperado_g (lo que decía el registro
 *   antes de pescar), peso_medido_g (muestra al pescar) y cerro_lote (la cosecha dejó la siembra en cero).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tamanos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('especie_id')->constrained('especies')->restrictOnDelete();
            $table->string('nombre', 60);
            $table->decimal('peso_min_g', 8, 1)->nullable();
            $table->decimal('peso_max_g', 8, 1)->nullable();
            $table->unsignedSmallInteger('orden')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'especie_id']);
        });
        DB::statement('CREATE UNIQUE INDEX tamanos_nombre_unico_por_especie ON tamanos (especie_id, LOWER(nombre))');

        Schema::create('procesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('nombre', 60);
            $table->unsignedSmallInteger('orden')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        DB::statement('CREATE UNIQUE INDEX procesos_nombre_unico_por_finca ON procesos (tenant_id, LOWER(nombre))');

        Schema::create('presentaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('especie_id')->constrained('especies')->restrictOnDelete();
            $table->foreignId('tamano_id')->constrained('tamanos')->restrictOnDelete();
            $table->foreignId('proceso_id')->constrained('procesos')->restrictOnDelete();
            $table->string('color_cabuya', 40)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tamano_id', 'proceso_id']);
            $table->index(['tenant_id', 'especie_id']);
        });

        Schema::create('pesajes_limpio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('jornada_id')->constrained('jornadas')->restrictOnDelete();
            $table->foreignId('presentacion_id')->constrained('presentaciones')->restrictOnDelete();
            $table->string('tipo', 15);
            $table->unsignedSmallInteger('canastas')->default(0);
            $table->decimal('tara_unitaria_kg', 6, 2)->default(0);
            $table->decimal('bruto_kg', 10, 2);
            $table->decimal('tara_kg', 10, 2)->default(0);
            $table->decimal('limpio_kg', 10, 2);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'jornada_id']);
        });
        DB::statement("ALTER TABLE pesajes_limpio ADD CONSTRAINT pesajes_limpio_cuentas CHECK (
            tipo IN ('canasta', 'canastilla', 'total')
            AND tara_unitaria_kg >= 0
            AND tara_kg = canastas * tara_unitaria_kg
            AND limpio_kg = bruto_kg - tara_kg
            AND limpio_kg > 0
            AND (tipo <> 'total' OR (canastas = 0 AND tara_kg = 0))
            AND (tipo = 'total' OR canastas >= 1)
        )");

        Schema::create('reproducciones_jornada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('jornada_lago_id')->constrained('jornada_lagos')->restrictOnDelete();
            $table->foreignId('especie_id')->constrained('especies')->restrictOnDelete();
            $table->decimal('kg', 10, 2);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'jornada_lago_id']);
        });
        DB::statement('ALTER TABLE reproducciones_jornada ADD CONSTRAINT reproducciones_jornada_kg_positivo CHECK (kg > 0)');

        Schema::create('jornada_rendimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('jornada_id')->constrained('jornadas')->restrictOnDelete();
            $table->foreignId('especie_id')->constrained('especies')->restrictOnDelete();
            $table->decimal('entero_kg', 10, 2);
            $table->decimal('reproduccion_kg', 10, 2)->default(0);
            $table->decimal('limpio_kg', 10, 2)->nullable();
            $table->decimal('rendimiento', 5, 2)->nullable();
            $table->decimal('referencia', 5, 2)->nullable();
            $table->string('fuente_referencia', 10)->nullable();
            $table->boolean('bajo')->default(false);
            $table->string('motivo', 300)->nullable();
            $table->timestamps();

            $table->unique(['jornada_id', 'especie_id']);
            $table->index(['tenant_id', 'especie_id']);
        });
        DB::statement("ALTER TABLE jornada_rendimientos ADD CONSTRAINT jornada_rendimientos_fuente_valida CHECK (fuente_referencia IS NULL OR fuente_referencia IN ('catalogo', 'promedio'))");

        Schema::table('especies', function (Blueprint $table) {
            $table->decimal('rendimiento_referencia', 5, 2)->nullable();
        });

        Schema::table('cosechas', function (Blueprint $table) {
            $table->decimal('limpio_kg', 10, 2)->nullable();
            $table->unsignedInteger('peces_esperados')->nullable();
            $table->decimal('peso_esperado_g', 8, 2)->nullable();
            $table->decimal('peso_medido_g', 8, 2)->nullable();
            $table->boolean('cerro_lote')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cosechas', function (Blueprint $table) {
            $table->dropColumn(['limpio_kg', 'peces_esperados', 'peso_esperado_g', 'peso_medido_g', 'cerro_lote']);
        });

        Schema::table('especies', function (Blueprint $table) {
            $table->dropColumn('rendimiento_referencia');
        });

        Schema::dropIfExists('jornada_rendimientos');
        Schema::dropIfExists('reproducciones_jornada');
        Schema::dropIfExists('pesajes_limpio');
        Schema::dropIfExists('presentaciones');
        Schema::dropIfExists('procesos');
        Schema::dropIfExists('tamanos');
    }
};
