<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de solo inserción. Además del bloqueo en el modelo, la base lo impone:
 * un trigger rechaza UPDATE, DELETE y TRUNCATE, y el usuario de la aplicación no tiene esos permisos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('rol', 20);           // rol con el que actuó (uno de los 4)
            $table->string('accion', 20);        // racion, mortalidad, traslado, muestreo, ronda, novedad, cosecha, venta
            $table->foreignId('lago_id')->nullable()->constrained('lagos')->restrictOnDelete();
            $table->jsonb('detalle');            // copia de los datos de ese momento
            $table->timestamp('ocurrido_at', 6); // instante exacto (UTC)
            $table->string('zona_horaria', 64);  // zona de la finca en ese momento
            $table->timestamp('created_at', 6)->useCurrent();

            $table->index(['tenant_id', 'ocurrido_at']);
            $table->index(['tenant_id', 'user_id']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION bitacora_solo_insercion() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'La bitácora es de solo inserción: % no está permitido', TG_OP;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER bitacora_sin_cambios BEFORE UPDATE OR DELETE ON bitacora
                FOR EACH ROW EXECUTE FUNCTION bitacora_solo_insercion();

            CREATE TRIGGER bitacora_sin_vaciado BEFORE TRUNCATE ON bitacora
                FOR EACH STATEMENT EXECUTE FUNCTION bitacora_solo_insercion();

            REVOKE UPDATE, DELETE, TRUNCATE ON bitacora FROM PUBLIC;
            REVOKE UPDATE, DELETE, TRUNCATE ON bitacora FROM CURRENT_USER;
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
        DB::unprepared('DROP FUNCTION IF EXISTS bitacora_solo_insercion()');
    }
};
