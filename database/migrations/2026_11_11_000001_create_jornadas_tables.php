<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F6a: Jornada de proceso (docs/diseno/dia-de-proceso.md, temas 2 a 5). Solo agrega: lo que ya existe sigue igual.
 * - jornadas: un día de proceso. Estado abierta, cerrada o cancelada (texto con CHECK, sin enum). Una sola jornada
 *   activa (no cancelada) por finca y fecha: índice único parcial. sin_alimentacion se copia del ajuste de la finca
 *   al crearla.
 * - jornada_lagos: los lagos que se pescan ese día, en el orden en que se agregaron. resultado (vacio o
 *   quedo_pescado) se llena al cerrar; cierre guarda lo que pasó con cada siembra (reparto, peces, diferencia).
 * - pesajes_entero: cada pesada del entero de un lago y una especie (como pesajes_cosecha), o el total escrito
 *   (tipo «total», sin canastas). entero_kg = bruto_kg − tara_kg y tara_kg = canastas × tara_unitaria_kg.
 * - cosechas.jornada_id: las cosechas que crea el cierre de una jornada; vacío en la cosecha manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jornadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->date('fecha');
            $table->string('estado', 12)->default('abierta');
            $table->boolean('sin_alimentacion')->default(false);
            $table->string('observaciones', 500)->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('cerrada_at')->nullable();
            $table->foreignId('cerrada_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelada_at')->nullable();
            $table->foreignId('cancelada_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'fecha']);
            $table->index(['tenant_id', 'estado']);
        });

        DB::statement("ALTER TABLE jornadas ADD CONSTRAINT jornadas_estado_valido CHECK (estado IN ('abierta', 'cerrada', 'cancelada'))");
        DB::statement("CREATE UNIQUE INDEX jornadas_una_activa_por_fecha ON jornadas (tenant_id, fecha) WHERE estado <> 'cancelada'");

        Schema::create('jornada_lagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('jornada_id')->constrained('jornadas')->restrictOnDelete();
            $table->foreignId('lago_id')->constrained('lagos')->restrictOnDelete();
            $table->unsignedSmallInteger('orden')->default(1);
            $table->string('resultado', 15)->nullable();
            $table->jsonb('cierre')->nullable();
            $table->timestamps();

            $table->unique(['jornada_id', 'lago_id']);
            $table->index(['tenant_id', 'lago_id']);
        });

        DB::statement("ALTER TABLE jornada_lagos ADD CONSTRAINT jornada_lagos_resultado_valido CHECK (resultado IS NULL OR resultado IN ('vacio', 'quedo_pescado'))");

        Schema::create('pesajes_entero', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('jornada_lago_id')->constrained('jornada_lagos')->restrictOnDelete();
            $table->foreignId('especie_id')->constrained('especies')->restrictOnDelete();
            $table->string('tipo', 15);
            $table->unsignedSmallInteger('canastas')->default(0);
            $table->decimal('tara_unitaria_kg', 6, 2)->default(0);
            $table->decimal('bruto_kg', 10, 2);
            $table->decimal('tara_kg', 10, 2)->default(0);
            $table->decimal('entero_kg', 10, 2);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'jornada_lago_id']);
        });

        DB::statement("ALTER TABLE pesajes_entero ADD CONSTRAINT pesajes_entero_cuentas CHECK (
            tipo IN ('canasta', 'canastilla', 'total')
            AND tara_unitaria_kg >= 0
            AND tara_kg = canastas * tara_unitaria_kg
            AND entero_kg = bruto_kg - tara_kg
            AND entero_kg > 0
            AND (tipo <> 'total' OR (canastas = 0 AND tara_kg = 0))
            AND (tipo = 'total' OR canastas >= 1)
        )");

        Schema::table('cosechas', function (Blueprint $table) {
            $table->foreignId('jornada_id')->nullable()->constrained('jornadas')->restrictOnDelete();
            $table->index(['tenant_id', 'jornada_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cosechas', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'jornada_id']);
            $table->dropConstrainedForeignId('jornada_id');
        });

        Schema::dropIfExists('pesajes_entero');
        Schema::dropIfExists('jornada_lagos');
        Schema::dropIfExists('jornadas');
    }
};
