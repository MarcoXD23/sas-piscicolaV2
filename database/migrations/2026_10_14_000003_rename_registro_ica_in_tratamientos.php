<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El registro del producto no es de un solo país: pasa a llamarse registro sanitario.
     */
    public function up(): void
    {
        Schema::table('tratamientos', function (Blueprint $table) {
            $table->renameColumn('registro_ica', 'registro_sanitario');
        });
    }

    public function down(): void
    {
        Schema::table('tratamientos', function (Blueprint $table) {
            $table->renameColumn('registro_sanitario', 'registro_ica');
        });
    }
};
