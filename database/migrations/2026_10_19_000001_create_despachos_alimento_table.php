<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bodega en dos pasos: el propietario registra el despacho de concentrado y el administrador lo recibe.
 * La recepción sigue siendo una entrada en movimientos_alimento (ahora con despacho_id opcional: vacío = sin despacho),
 * así las entradas históricas quedan como recepciones sin despacho, sin transformar datos.
 * El stock nunca puede quedar negativo: además del bloqueo en la aplicación, la base lo exige.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('despachos_alimento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('fecha');
            $table->string('proveedor');
            $table->string('tipo_concentrado');
            $table->unsignedInteger('bultos');
            $table->decimal('peso_bulto_kg', 8, 2);
            $table->decimal('cantidad_kg', 12, 2);
            $table->string('lote');
            $table->timestamp('revisado_at')->nullable();  // el propietario revisó la diferencia con lo recibido
            $table->timestamps();

            $table->index(['tenant_id', 'fecha']);
        });

        Schema::table('movimientos_alimento', function (Blueprint $table) {
            $table->foreignId('despacho_id')->nullable()->after('lago_id')->constrained('despachos_alimento')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE stocks_alimento ADD CONSTRAINT stocks_alimento_cantidad_no_negativa CHECK (cantidad_kg >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE stocks_alimento DROP CONSTRAINT IF EXISTS stocks_alimento_cantidad_no_negativa');

        Schema::table('movimientos_alimento', function (Blueprint $table) {
            $table->dropConstrainedForeignId('despacho_id');
        });

        Schema::dropIfExists('despachos_alimento');
    }
};
