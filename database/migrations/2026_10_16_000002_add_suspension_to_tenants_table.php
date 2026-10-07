<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una finca suspendida (suspendida_at con valor) no puede iniciar sesión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('suspendida_at')->nullable();
            $table->string('motivo_suspension', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['suspendida_at', 'motivo_suspension']);
        });
    }
};
