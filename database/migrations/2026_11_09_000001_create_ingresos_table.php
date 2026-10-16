<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ingresos a las cuentas de la finca, exitosos y fallidos (web, segundo paso y API), para «Mis últimos ingresos»,
     * «Ingresos del personal» y el aviso de equipo nuevo.
     * - Solo de personas que existen: un correo inventado no se guarda (no se guarda lo que escriban).
     * - Sin la IP completa: solo la red recortada (los 3 primeros bloques en IPv4; 48 bits en IPv6).
     * - El equipo es el navegador y el sistema leídos del user agent; equipo_hash es el sha256 del identificador al azar
     *   de la cookie del equipo (para saber si es nuevo), nunca el identificador.
     * - motivo y canal son texto (sin enum de base de datos). Se guardan 90 días (limpieza diaria).
     */
    public function up(): void
    {
        Schema::create('ingresos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('exitoso');
            $table->string('motivo', 30)->nullable();
            $table->string('canal', 10);
            $table->string('equipo', 120);
            $table->string('red', 64)->nullable();
            $table->string('equipo_hash', 64)->nullable();
            $table->boolean('equipo_nuevo')->default(false);
            $table->timestamp('aviso_cerrado_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingresos');
    }
};
