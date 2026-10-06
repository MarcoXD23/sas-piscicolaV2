<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una siembra está activa mientras finalizada_at sea null. Permite policultivo
     * (varias siembras activas en un lago) y volver a sembrar un lago ya usado.
     */
    public function up(): void
    {
        Schema::table('siembras', function (Blueprint $table) {
            $table->timestamp('finalizada_at')->nullable()->after('biomasa_kg');
            $table->index(['lago_id', 'finalizada_at']);
        });
    }

    public function down(): void
    {
        Schema::table('siembras', function (Blueprint $table) {
            $table->dropIndex(['lago_id', 'finalizada_at']);
            $table->dropColumn('finalizada_at');
        });
    }
};
