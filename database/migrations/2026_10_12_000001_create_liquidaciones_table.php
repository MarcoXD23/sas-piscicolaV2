<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nómina semanal: tarifas por persona y liquidaciones (registro histórico de cada pago).
     * Valores en pesos colombianos enteros.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('valor_hora')->nullable();
            $table->unsignedBigInteger('valor_hora_extra')->nullable();
        });

        Schema::create('liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('creada_por')->constrained('users')->restrictOnDelete();
            $table->date('semana_inicio'); // lunes
            $table->date('semana_fin');    // domingo
            $table->decimal('horas_turnos', 7, 2);
            $table->unsignedBigInteger('valor_hora');
            $table->unsignedBigInteger('valor_turnos');
            $table->decimal('horas_extra', 7, 2);
            $table->unsignedBigInteger('valor_hora_extra');
            $table->unsignedBigInteger('valor_horas_extra');
            $table->unsignedBigInteger('bruto');
            $table->unsignedBigInteger('descuento_pescado')->default(0);
            $table->unsignedBigInteger('total_pagar');
            $table->timestamp('pagada_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'user_id', 'semana_inicio']);
        });

        Schema::table('abonos', function (Blueprint $table) {
            // Abonos que salen de un descuento de nómina
            $table->foreignId('liquidacion_id')->nullable()->after('venta_id')->constrained('liquidaciones')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE liquidaciones ADD CONSTRAINT liquidaciones_totales_validos CHECK (descuento_pescado <= bruto AND total_pagar = bruto - descuento_pescado)');
    }

    public function down(): void
    {
        Schema::table('abonos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('liquidacion_id');
        });

        Schema::dropIfExists('liquidaciones');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['valor_hora', 'valor_hora_extra']);
        });
    }
};
