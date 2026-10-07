<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acceso de plataforma (dueño del software). No es un rol de finca: tabla y guard aparte, sin tenant_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administradores_plataforma', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administradores_plataforma');
    }
};
