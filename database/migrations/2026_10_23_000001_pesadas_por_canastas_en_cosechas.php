<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pesaje de la cosecha como en el cuaderno: cada fila es una PESADA de varias canastas o canastillas.
 * - pesajes_cosecha: tipo (canasta o canastilla), canastas (la Cantidad) y tara_unitaria_kg (peso de UNA vacía).
 *   El descuento (tara_kg) = canastas × tara_unitaria_kg; el peso limpio (neto_kg) = bruto_kg − tara_kg.
 *   Los pesajes ya guardados quedan intactos: cada uno es una pesada de 1 canasta con su tara de siempre.
 * - cosechas: destino del pedido (vacío en las viejas) y quién despachó (opcional).
 * - tenants: peso por defecto de una canasta y de una canastilla vacías.
 * Los nombres internos (bruto_kg, tara_kg, neto_kg) no cambian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesajes_cosecha', function (Blueprint $table) {
            $table->string('tipo', 15)->default('canasta');   // canasta o canastilla (validado en la aplicación)
            $table->unsignedSmallInteger('canastas')->default(1);
            $table->decimal('tara_unitaria_kg', 6, 2)->nullable();
        });

        // Pesajes existentes: una canasta con su tara de siempre como peso de una vacía
        DB::statement('UPDATE pesajes_cosecha SET tara_unitaria_kg = tara_kg WHERE tara_unitaria_kg IS NULL');
        DB::statement('ALTER TABLE pesajes_cosecha ALTER COLUMN tara_unitaria_kg SET NOT NULL');
        DB::statement("ALTER TABLE pesajes_cosecha ADD CONSTRAINT pesajes_cosecha_descuento_multiplicado CHECK (
            canastas >= 1 AND tara_unitaria_kg >= 0 AND tipo IN ('canasta', 'canastilla') AND tara_kg = canastas * tara_unitaria_kg
        )");

        Schema::table('cosechas', function (Blueprint $table) {
            $table->string('destino', 120)->nullable();
            $table->foreignId('despachado_por_id')->nullable()->constrained('users')->restrictOnDelete();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('peso_canasta_kg', 6, 2)->default(2.00);
            $table->decimal('peso_canastilla_kg', 6, 2)->default(1.50);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['peso_canasta_kg', 'peso_canastilla_kg']);
        });

        Schema::table('cosechas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('despachado_por_id');
            $table->dropColumn('destino');
        });

        DB::statement('ALTER TABLE pesajes_cosecha DROP CONSTRAINT IF EXISTS pesajes_cosecha_descuento_multiplicado');
        Schema::table('pesajes_cosecha', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'canastas', 'tara_unitaria_kg']);
        });
    }
};
