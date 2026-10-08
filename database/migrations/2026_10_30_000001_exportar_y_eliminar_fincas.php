<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exportar y eliminar los datos de una finca.
 * - tenants: cuándo se pidió la eliminación y cuándo se borra (periodo de gracia), y si la suspensión vino de ella.
 * - exportaciones: paquetes ZIP de los datos de la finca (vencen a las 24 horas).
 * - Bitácora: sigue siendo de solo inserción. Solo la función borrar_bitacora_finca() (SECURITY DEFINER) puede borrar,
 *   y únicamente las filas de una finca cuyo plazo de eliminación ya venció; el trigger lo comprueba con una marca
 *   local de la transacción. La app solo recibe permiso para ejecutar esa función (ver piscicola:usuario-base).
 * No cambia datos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('eliminacion_solicitada_at')->nullable();
            $table->timestamp('eliminacion_programada_at')->nullable();
            $table->boolean('suspendida_por_eliminacion')->default(false);
        });

        Schema::create('exportaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('estado', 15)->default('pendiente');   // pendiente, lista o fallo (validado en la aplicación)
            $table->string('ruta', 255)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->timestamp('vence_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
        DB::statement("ALTER TABLE exportaciones ADD CONSTRAINT exportaciones_estado_valido CHECK (estado IN ('pendiente', 'lista', 'fallo'))");

        DB::unprepared(<<<'SQL'
            -- El trigger deja pasar el borrado SOLO de las filas de la finca marcada en esta transacción
            CREATE OR REPLACE FUNCTION bitacora_solo_insercion() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.tenant_id::text = current_setting('piscicola.finca_eliminada', true) THEN
                        RETURN OLD;
                    END IF;
                END IF;
                RAISE EXCEPTION 'La bitácora es de solo inserción: % no está permitido', TG_OP;
            END;
            $$ LANGUAGE plpgsql;

            -- Única vía para borrar bitácora: la de una finca cuyo plazo de eliminación ya venció
            CREATE OR REPLACE FUNCTION borrar_bitacora_finca(p_tenant bigint) RETURNS integer
                LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                borradas integer;
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM tenants WHERE id = p_tenant
                               -- Las horas se guardan en UTC (timestamp sin zona): se compara con la hora UTC, no la de la sesión
                               AND eliminacion_programada_at IS NOT NULL AND eliminacion_programada_at <= (now() AT TIME ZONE 'UTC')) THEN
                    RAISE EXCEPTION 'La finca % no tiene vencido su plazo de eliminación', p_tenant;
                END IF;
                PERFORM set_config('piscicola.finca_eliminada', p_tenant::text, true);
                DELETE FROM bitacora WHERE tenant_id = p_tenant;
                GET DIAGNOSTICS borradas = ROW_COUNT;
                PERFORM set_config('piscicola.finca_eliminada', '', true);
                RETURN borradas;
            END;
            $$;

            REVOKE ALL ON FUNCTION borrar_bitacora_finca(bigint) FROM PUBLIC;
            -- El dueño de la función necesita el permiso de borrar (el trigger sigue protegiendo cada fila)
            GRANT DELETE ON bitacora TO CURRENT_USER;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS borrar_bitacora_finca(bigint);
            REVOKE DELETE ON bitacora FROM CURRENT_USER;
            CREATE OR REPLACE FUNCTION bitacora_solo_insercion() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'La bitácora es de solo inserción: % no está permitido', TG_OP;
            END;
            $$ LANGUAGE plpgsql;
            SQL);
        Schema::dropIfExists('exportaciones');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['eliminacion_solicitada_at', 'eliminacion_programada_at', 'suspendida_por_eliminacion']);
        });
    }
};
