<?php

use App\Support\CatalogoEspecies;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de especies por finca. Cada finca (también las existentes) recibe las 6 especies por defecto, y las siembras
 * pasan de guardar la especie como texto (siembras.especie) a referenciar su fila (siembras.especie_id).
 * Un texto que no esté en el catálogo se conserva como especie propia (agua cálida, sin rangos) para revisarla.
 * Al final se borra la columna vieja: no queda el texto en uso. Se usa DB::table: la migración no depende del filtro de finca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('especies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('clave', 40)->nullable();          // especie por defecto: une sus textos y foto de referencia
            $table->string('nombre', 80);
            $table->string('nombre_cientifico', 120)->nullable();
            $table->string('clima', 10);                      // calido o frio (validado en la aplicación y con CHECK)
            $table->decimal('temp_min', 4, 1)->nullable();
            $table->decimal('temp_max', 4, 1)->nullable();
            $table->decimal('oxigeno_min', 4, 1)->nullable();
            $table->decimal('ph_min', 4, 2)->nullable();
            $table->decimal('ph_max', 4, 2)->nullable();
            $table->string('alimentacion', 20)->nullable();   // perfil de la tabla de ración: calido, frio o detritivoro
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'activo']);
        });

        DB::statement('CREATE UNIQUE INDEX especies_nombre_unico_por_finca ON especies (tenant_id, LOWER(nombre))');
        DB::statement("ALTER TABLE especies ADD CONSTRAINT especies_valores_validos CHECK (
            clima IN ('calido', 'frio')
            AND (alimentacion IS NULL OR alimentacion IN ('calido', 'frio', 'detritivoro'))
            AND (temp_min IS NULL OR temp_max IS NULL OR temp_min <= temp_max)
            AND (ph_min IS NULL OR ph_max IS NULL OR ph_min <= ph_max)
        )");

        // Las 6 especies por defecto en cada finca existente
        $ahora = now();
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach (CatalogoEspecies::porDefecto() as $fila) {
                DB::table('especies')->insert($fila + ['tenant_id' => $tenantId, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
            }
        }

        Schema::table('siembras', function (Blueprint $table) {
            $table->foreignId('especie_id')->nullable()->constrained('especies')->restrictOnDelete();
        });

        // Cada siembra a la especie de SU finca con la misma clave
        DB::statement('UPDATE siembras s SET especie_id = e.id FROM especies e WHERE e.tenant_id = s.tenant_id AND e.clave = LOWER(s.especie)');

        // Textos que no estaban en el catálogo: se conservan como especie propia, sin rangos, para revisarla
        $sueltas = DB::table('siembras')->whereNull('especie_id')->select('tenant_id', 'especie')->distinct()->get();
        foreach ($sueltas as $suelta) {
            $id = DB::table('especies')->insertGetId(['tenant_id' => $suelta->tenant_id, 'nombre' => mb_substr(ucfirst($suelta->especie), 0, 80),
                'clima' => 'calido', 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
            DB::table('siembras')->where('tenant_id', $suelta->tenant_id)->where('especie', $suelta->especie)->update(['especie_id' => $id]);
        }

        DB::statement('ALTER TABLE siembras ALTER COLUMN especie_id SET NOT NULL');
        Schema::table('siembras', function (Blueprint $table) {
            $table->dropColumn('especie');
        });
    }

    public function down(): void
    {
        Schema::table('siembras', function (Blueprint $table) {
            $table->string('especie', 50)->nullable();
        });
        DB::statement('UPDATE siembras s SET especie = COALESCE(e.clave, LOWER(e.nombre)) FROM especies e WHERE e.id = s.especie_id');
        Schema::table('siembras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('especie_id');
        });
        Schema::dropIfExists('especies');
    }
};
