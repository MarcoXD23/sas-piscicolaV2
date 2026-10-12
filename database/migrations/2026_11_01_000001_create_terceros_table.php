<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Personas externas de la finca, sin cuenta en la app (jornaleros; más adelante clientes y proveedores), en una
     * sola tabla. El documento va cifrado con la clave de la app y una huella (HMAC) evita repetirlo en la finca.
     * Ventas y bitácora pueden apuntar a un tercero: la bitácora guarda solo su id (el nombre se resuelve al mostrarla,
     * así la anonimización la cubre).
     */
    public function up(): void
    {
        Schema::create('terceros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('nombres', 120);
            $table->string('apellidos', 120)->nullable();
            $table->text('documento')->nullable();             // cifrado
            $table->string('documento_huella', 64)->nullable(); // HMAC-SHA256 del documento normalizado
            $table->string('telefono', 30)->nullable();
            $table->boolean('es_jornalero')->default(false);
            $table->boolean('es_cliente')->default(false);
            $table->boolean('es_proveedor')->default(false);
            $table->string('categoria_habitual', 30)->nullable(); // validada en la aplicación (Tercero::CATEGORIAS)
            $table->boolean('activo')->default(true);
            $table->timestamp('anonimizado_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'es_jornalero', 'activo']);
        });

        // Un documento no se repite dentro de la finca (sí entre fincas)
        DB::statement('CREATE UNIQUE INDEX terceros_documento_unico ON terceros (tenant_id, documento_huella) WHERE documento_huella IS NOT NULL');

        Schema::table('ventas', function (Blueprint $table) {
            // Comprador sin cuenta (jornalero o cliente): el nombre de ese día sigue en «cliente»
            $table->foreignId('tercero_id')->nullable()->after('comprador_id')->constrained('terceros')->restrictOnDelete();
            $table->index('tercero_id');
        });
        // Un solo comprador: empleado o tercero (o solo el nombre escrito, como las ventas que ya existen)
        DB::statement('ALTER TABLE ventas ADD CONSTRAINT ventas_un_solo_comprador CHECK (comprador_id IS NULL OR tercero_id IS NULL)');

        Schema::table('bitacora', function (Blueprint $table) {
            $table->foreignId('tercero_id')->nullable()->after('persona_id')->constrained('terceros')->restrictOnDelete();
            $table->index(['tenant_id', 'tercero_id']);
        });
    }

    public function down(): void
    {
        Schema::table('bitacora', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'tercero_id']);
            $table->dropConstrainedForeignId('tercero_id');
        });
        DB::statement('ALTER TABLE ventas DROP CONSTRAINT IF EXISTS ventas_un_solo_comprador');
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex(['tercero_id']);
            $table->dropConstrainedForeignId('tercero_id');
        });
        Schema::dropIfExists('terceros');
    }
};
