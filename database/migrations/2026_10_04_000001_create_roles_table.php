<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo fijo del sistema: solo los 4 roles permitidos.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        $ahora = now();

        DB::table('roles')->insert(array_map(
            fn (string $nombre) => ['nombre' => $nombre, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['propietario', 'administrador', 'trabajador', 'celador'],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};

