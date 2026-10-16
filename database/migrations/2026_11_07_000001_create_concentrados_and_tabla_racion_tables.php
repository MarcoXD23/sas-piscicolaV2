<?php

use App\Models\Concentrado;
use App\Models\TablaRacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concentrados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->string('nombre_corto', 60)->nullable();
            $table->decimal('proteina_porcentaje', 5, 2)->nullable();
            $table->string('pellet_mm', 40)->nullable();
            $table->decimal('peso_bulto_kg', 6, 2)->default(Concentrado::PESO_BULTO_POR_DEFECTO);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'nombre']);
        });

        Schema::create('tabla_racion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('especie_id')->index();
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

        Schema::table('tenants', function (Blueprint $table) {
            $table->smallInteger('alerta_muestreo_dias')->default(30)->after('alerta_despacho_dias');
        });

        $ahora = now();
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            TablaRacion::cargarPorDefecto((int) $tenantId);

            // Migrar los tipos de concentrado que ya existan en stock, movimientos o despachos de la finca
            $nombres = collect()
                ->merge(DB::table('stocks_alimento')->where('tenant_id', $tenantId)->pluck('tipo_concentrado'))
                ->merge(DB::table('movimientos_alimento')->where('tenant_id', $tenantId)->pluck('tipo_concentrado'))
                ->merge(DB::table('despachos_alimento')->where('tenant_id', $tenantId)->pluck('tipo_concentrado'))
                ->map(fn ($nombre) => trim((string) $nombre))
                ->filter()
                ->unique()
                ->values();

            foreach ($nombres as $nombre) {
                $pesoBulto = DB::table('despachos_alimento')
                    ->where('tenant_id', $tenantId)
                    ->where('tipo_concentrado', $nombre)
                    ->whereNotNull('peso_bulto_kg')
                    ->orderByDesc('id')
                    ->value('peso_bulto_kg')
                    ?? DB::table('movimientos_alimento')
                        ->where('tenant_id', $tenantId)
                        ->where('tipo_concentrado', $nombre)
                        ->whereNotNull('peso_bulto_kg')
                        ->orderByDesc('id')
                        ->value('peso_bulto_kg')
                    ?? Concentrado::PESO_BULTO_POR_DEFECTO;

                $concentradoId = DB::table('concentrados')->insertGetId([
                    'tenant_id' => $tenantId,
                    'nombre' => $nombre,
                    'peso_bulto_kg' => (float) $pesoBulto > 0 ? (float) $pesoBulto : Concentrado::PESO_BULTO_POR_DEFECTO,
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);

                foreach (['stocks_alimento', 'movimientos_alimento', 'despachos_alimento'] as $tabla) {
                    DB::table($tabla)
                        ->where('tenant_id', $tenantId)
                        ->where('tipo_concentrado', $nombre)
                        ->update(['concentrado_id' => $concentradoId]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('alerta_muestreo_dias');
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

