<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso «listo para pescar» del trabajador al administrador. Solo puede haber uno pendiente por lago
 * (índice único parcial). Se atiende a mano o al registrar una cosecha de esa siembra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avisos_pesca', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('lago_id')->constrained('lagos')->restrictOnDelete();
            $table->foreignId('siembra_id')->constrained('siembras')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('nota', 500)->nullable();
            $table->string('estado', 15)->default('pendiente');   // pendiente, atendido (validado en la aplicación)
            $table->timestamp('reportado_at');
            $table->timestamp('atendido_at')->nullable();
            $table->foreignId('atendido_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'estado']);
        });

        DB::statement("CREATE UNIQUE INDEX avisos_pesca_un_pendiente_por_lago ON avisos_pesca (tenant_id, lago_id) WHERE estado = 'pendiente'");
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_pesca');
    }
};
