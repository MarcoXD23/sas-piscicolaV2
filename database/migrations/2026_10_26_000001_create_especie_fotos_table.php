<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fotos reales de cada especie (máximo 5). La base también lo garantiza: el orden solo va de 1 a 5 y no se repite
 * en una especie (restricción diferible, para poder reordenar dentro de una transacción).
 * Los archivos viven en el disco privado «fotos»; aquí solo sus rutas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('especie_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('especie_id')->constrained('especies')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();   // quién la subió
            $table->string('ruta_original', 255);
            $table->string('ruta_ligera', 255);
            $table->string('tipo_original', 20);                                // image/jpeg, image/png o image/webp
            $table->unsignedInteger('bytes_original');
            $table->unsignedInteger('bytes_ligera');
            $table->unsignedInteger('ancho');
            $table->unsignedInteger('alto');
            $table->unsignedSmallInteger('orden');
            $table->string('autor', 150)->nullable();                           // «Autor o fuente»
            $table->string('licencia', 150)->nullable();
            $table->timestamps();                                               // created_at: fecha de subida (UTC)

            $table->index(['tenant_id', 'especie_id']);
        });

        DB::statement("ALTER TABLE especie_fotos ADD CONSTRAINT especie_fotos_orden_1_a_5 CHECK (orden BETWEEN 1 AND 5)");
        DB::statement("ALTER TABLE especie_fotos ADD CONSTRAINT especie_fotos_tipo_valido CHECK (tipo_original IN ('image/jpeg', 'image/png', 'image/webp'))");
        DB::statement('ALTER TABLE especie_fotos ADD CONSTRAINT especie_fotos_orden_unico UNIQUE (especie_id, orden) DEFERRABLE INITIALLY DEFERRED');
    }

    public function down(): void
    {
        Schema::dropIfExists('especie_fotos');
    }
};
