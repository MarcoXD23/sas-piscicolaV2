<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cosechas de pescado, ventas (de contado o a crédito) y abonos a los créditos.
     * Los valores en dinero son pesos colombianos enteros.
     */
    public function up(): void
    {
        Schema::create('cosechas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('siembra_id')->constrained('siembras')->restrictOnDelete();
            $table->foreignId('lago_id')->constrained('lagos')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha');
            $table->unsignedInteger('peces');
            $table->decimal('kg', 12, 2);
            $table->decimal('peso_promedio_g', 10, 2);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['lago_id', 'fecha']);
        });

        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('cosecha_id')->constrained('cosechas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // quién registró
            $table->foreignId('comprador_id')->nullable()->constrained('users')->restrictOnDelete(); // si compra un empleado
            $table->string('cliente');
            $table->date('fecha');
            $table->decimal('kg', 12, 2);
            $table->unsignedBigInteger('precio_kg');
            $table->unsignedBigInteger('total');
            $table->string('forma_pago', 20); // contado o credito (validado en la aplicación)
            $table->unsignedBigInteger('saldo')->default(0);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'fecha']);
            $table->index('comprador_id');
        });

        Schema::create('abonos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha');
            $table->unsignedBigInteger('valor');
            $table->string('medio', 20); // efectivo, transferencia o nomina (validado en la aplicación)
            $table->string('observaciones')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('venta_id');
        });

        DB::statement('ALTER TABLE cosechas ADD CONSTRAINT cosechas_valores_positivos CHECK (peces > 0 AND kg > 0)');
        DB::statement('ALTER TABLE ventas ADD CONSTRAINT ventas_valores_validos CHECK (kg > 0 AND saldo <= total)');
        DB::statement('ALTER TABLE abonos ADD CONSTRAINT abonos_valor_positivo CHECK (valor > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('abonos');
        Schema::dropIfExists('ventas');
        Schema::dropIfExists('cosechas');
    }
};
