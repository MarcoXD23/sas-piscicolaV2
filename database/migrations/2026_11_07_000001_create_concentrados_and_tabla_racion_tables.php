<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PESO_BULTO_POR_DEFECTO = 40.0;

    /**
     * Tramos iniciales por perfil de alimentación: copia fija de TablaRacion::TRAMOS_POR_PERFIL al crear esta migración
     * (el % y las tomas de las tablas de antes, con los datos de la finca piloto en el perfil cálido; tema 15.3).
     * [peso desde (g), peso hasta (g), % de la biomasa, tomas al día, proteína (%), pellet]
     */
    private const TRAMOS = [
        'calido' => [
            [0.1, 5.0, 8.0, 5, 45.0, 'Harina'],
            [5.01, 20.0, 4.1, 4, 36.0, null],
            [20.01, 50.0, 4.0, 4, null, null],
            [50.01, 100.0, 3.0, 3, null, null],
            [100.01, 200.0, 2.5, 3, null, null],
            [200.01, 300.0, 1.5, 3, 24.0, null],
            [300.01, 500.0, 1.3, 2, null, null],
            [500.01, 99999.0, 1.2, 2, null, null],
        ],
        'detritivoro' => [
            [0.1, 5.0, 4.0, 4, null, null],
            [5.01, 20.0, 3.0, 3, null, null],
            [20.01, 50.0, 2.0, 3, null, null],
            [50.01, 100.0, 1.5, 2, null, null],
            [100.01, 200.0, 1.2, 2, null, null],
            [200.01, 300.0, 1.0, 2, null, null],
            [300.01, 500.0, 0.8, 2, null, null],
            [500.01, 99999.0, 0.6, 2, null, null],
        ],
        'frio' => [
            [0.1, 5.0, 5.0, 6, null, null],
            [5.01, 20.0, 3.5, 5, null, null],
            [20.01, 50.0, 2.5, 4, null, null],
            [50.01, 100.0, 2.0, 3, null, null],
            [100.01, 200.0, 1.6, 3, null, null],
            [200.01, 99999.0, 1.2, 2, null, null],
        ],
    ];

    /**
     * F3: catálogo de concentrados y tabla de ración por finca y especie.
     * - concentrados: nombre, nombre corto (el color del hilo en la finca piloto), proteína, pellet y peso del bulto.
     * - tabla_racion: tramos de peso con % de la biomasa, tomas, proteína, pellet y concentrado sugerido.
     * - concentrado_id en stock, movimientos y despachos: liga cada registro al catálogo. tipo_concentrado (texto) se
     *   queda: los registros guardados no cambian y la cola sin conexión de los teléfonos sigue enviando el nombre.
     * - reglas_fase.concentrado_id: el concentrado que le corresponde a una fase y especie (opcional).
     * El plazo del muestreo es un ajuste de ajustes_finca (sin columna). Los datos se cargan con DB::table, sin modelos.
     * Toca datos de la bodega: sacar un respaldo antes de correrla en producción (piscicola:respaldar).
     */
    public function up(): void
    {
        Schema::create('concentrados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->string('nombre_corto', 60)->nullable();
            $table->decimal('proteina_porcentaje', 5, 2)->nullable();
            $table->string('pellet_mm', 40)->nullable();
            $table->decimal('peso_bulto_kg', 6, 2)->default(self::PESO_BULTO_POR_DEFECTO);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'nombre']);
        });

        Schema::create('tabla_racion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('especie_id')->constrained('especies')->cascadeOnDelete();
            $table->decimal('peso_desde_g', 8, 2);
            $table->decimal('peso_hasta_g', 8, 2);
            $table->decimal('porcentaje_biomasa', 5, 2);
            $table->smallInteger('tomas_dia');
            $table->decimal('proteina_porcentaje', 5, 2)->nullable();
            $table->string('pellet', 40)->nullable();
            $table->foreignId('concentrado_id')->nullable()->constrained('concentrados')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'especie_id', 'peso_hasta_g']);
        });

        foreach (['stocks_alimento', 'movimientos_alimento', 'despachos_alimento'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->foreignId('concentrado_id')->nullable()->after('tenant_id')->constrained('concentrados')->nullOnDelete();
            });
        }

        Schema::table('reglas_fase', function (Blueprint $table) {
            $table->foreignId('concentrado_id')->nullable()->constrained('concentrados')->nullOnDelete();
        });

        $ahora = now();
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            // Tabla de ración inicial de cada especie con perfil de alimentación
            foreach (DB::table('especies')->where('tenant_id', $tenantId)->whereNotNull('alimentacion')->get(['id', 'alimentacion']) as $especie) {
                foreach (self::TRAMOS[$especie->alimentacion] ?? [] as [$desde, $hasta, $porcentaje, $tomas, $proteina, $pellet]) {
                    DB::table('tabla_racion')->insert([
                        'tenant_id' => $tenantId, 'especie_id' => $especie->id,
                        'peso_desde_g' => $desde, 'peso_hasta_g' => $hasta, 'porcentaje_biomasa' => $porcentaje, 'tomas_dia' => $tomas,
                        'proteina_porcentaje' => $proteina, 'pellet' => $pellet,
                        'created_at' => $ahora, 'updated_at' => $ahora,
                    ]);
                }
            }

            // Los concentrados que ya están en la bodega de la finca pasan al catálogo, con su mismo nombre
            $nombres = collect()
                ->merge(DB::table('stocks_alimento')->where('tenant_id', $tenantId)->pluck('tipo_concentrado'))
                ->merge(DB::table('movimientos_alimento')->where('tenant_id', $tenantId)->pluck('tipo_concentrado'))
                ->merge(DB::table('despachos_alimento')->where('tenant_id', $tenantId)->pluck('tipo_concentrado'))
                ->map(fn ($nombre) => trim((string) $nombre))
                ->filter()
                ->unique()
                ->values();

            foreach ($nombres as $nombre) {
                // El peso del bulto: el del último despacho o recepción con ese concentrado; si no hay, 40 kg
                $pesoBulto = (float) (DB::table('despachos_alimento')->where('tenant_id', $tenantId)->where('tipo_concentrado', $nombre)
                    ->whereNotNull('peso_bulto_kg')->orderByDesc('id')->value('peso_bulto_kg')
                    ?? DB::table('movimientos_alimento')->where('tenant_id', $tenantId)->where('tipo_concentrado', $nombre)
                        ->whereNotNull('peso_bulto_kg')->orderByDesc('id')->value('peso_bulto_kg'));

                $concentradoId = DB::table('concentrados')->insertGetId([
                    'tenant_id' => $tenantId,
                    'nombre' => $nombre,
                    'peso_bulto_kg' => $pesoBulto > 0 ? $pesoBulto : self::PESO_BULTO_POR_DEFECTO,
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);

                // Solo se liga: el texto guardado no cambia
                foreach (['stocks_alimento', 'movimientos_alimento', 'despachos_alimento'] as $tabla) {
                    DB::table($tabla)->where('tenant_id', $tenantId)->where('tipo_concentrado', $nombre)->update(['concentrado_id' => $concentradoId]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('reglas_fase', function (Blueprint $table) {
            $table->dropConstrainedForeignId('concentrado_id');
        });

        foreach (['despachos_alimento', 'movimientos_alimento', 'stocks_alimento'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropConstrainedForeignId('concentrado_id');
            });
        }

        Schema::dropIfExists('tabla_racion');
        Schema::dropIfExists('concentrados');
    }
};
