<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F6c-1: venta rápida por jornada, precios, cupo y obsequios (docs/diseno/dia-de-proceso.md, temas 8 y 9).
 * - precios_especie: por especie, el precio general (particulares y exceso del cupo) y, si entra en el cupo, el de
 *   trabajador dentro del cupo. Solo los cambia el propietario; cada venta guarda los suyos.
 * - obsequios: pescado regalado en una jornada, con quién lo registró y quién lo autorizó (nunca la misma persona).
 * - ventas: las nuevas van por jornada y especie, sin cosecha. cosecha_id pasa a aceptar vacío y un CHECK exige
 *   cosecha, o jornada con especie: las ventas viejas, ligadas a su cosecha, siguen igual. kg_cupo y precio_cupo: la
 *   parte vendida dentro del cupo del trabajador (el resto va a precio_kg, el general). recibio_id: quién recibió el
 *   dinero de contado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precios_especie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('especie_id')->unique()->constrained('especies')->restrictOnDelete();
            $table->unsignedBigInteger('precio_general');
            $table->boolean('entra_en_cupo')->default(false);
            $table->unsignedBigInteger('precio_cupo')->nullable();
            $table->foreignId('cambiado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
        });
        DB::statement('ALTER TABLE precios_especie ADD CONSTRAINT precios_especie_cupo_con_precio CHECK (NOT entra_en_cupo OR precio_cupo IS NOT NULL)');

        Schema::create('obsequios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('jornada_id')->constrained('jornadas')->restrictOnDelete();
            $table->foreignId('especie_id')->constrained('especies')->restrictOnDelete();
            $table->foreignId('presentacion_id')->nullable()->constrained('presentaciones')->restrictOnDelete();
            $table->decimal('kg', 10, 2);
            $table->string('destinatario', 120);
            $table->string('nota', 300)->nullable();
            $table->foreignId('registrado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('autorizado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'jornada_id']);
            $table->index(['tenant_id', 'created_at']);
        });
        DB::statement('ALTER TABLE obsequios ADD CONSTRAINT obsequios_reglas CHECK (kg > 0 AND registrado_por <> autorizado_por)');

        DB::statement('ALTER TABLE ventas ALTER COLUMN cosecha_id DROP NOT NULL');
        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('jornada_id')->nullable()->constrained('jornadas')->restrictOnDelete();
            $table->foreignId('especie_id')->nullable()->constrained('especies')->restrictOnDelete();
            $table->decimal('kg_cupo', 12, 2)->nullable();
            $table->unsignedBigInteger('precio_cupo')->nullable();
            $table->foreignId('recibio_id')->nullable()->constrained('users')->restrictOnDelete();

            $table->index(['tenant_id', 'jornada_id']);
            $table->index(['tenant_id', 'tercero_id', 'fecha']);
        });
        DB::statement('ALTER TABLE ventas ADD CONSTRAINT ventas_cosecha_o_jornada CHECK (cosecha_id IS NOT NULL OR (jornada_id IS NOT NULL AND especie_id IS NOT NULL))');
    }

    /** Si ya hay ventas de jornada (sin cosecha), volver a exigir cosecha_id falla a propósito: no se borran ventas. */
    public function down(): void
    {
        DB::statement('ALTER TABLE ventas DROP CONSTRAINT IF EXISTS ventas_cosecha_o_jornada');
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'tercero_id', 'fecha']);
            $table->dropIndex(['tenant_id', 'jornada_id']);
            $table->dropConstrainedForeignId('recibio_id');
            $table->dropColumn(['kg_cupo', 'precio_cupo']);
            $table->dropConstrainedForeignId('especie_id');
            $table->dropConstrainedForeignId('jornada_id');
        });
        DB::statement('ALTER TABLE ventas ALTER COLUMN cosecha_id SET NOT NULL');

        Schema::dropIfExists('obsequios');
        Schema::dropIfExists('precios_especie');
    }
};
