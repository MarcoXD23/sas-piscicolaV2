<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persona afectada por un registro de la bitácora (por ejemplo, a quién se le cambiaron los roles).
 * Solo agrega una columna opcional: la bitácora sigue siendo de solo inserción y sus registros no se modifican.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bitacora', function (Blueprint $table) {
            $table->foreignId('persona_id')->nullable()->after('user_id')->constrained('users')->restrictOnDelete();
            $table->index(['tenant_id', 'persona_id']);
        });
    }

    public function down(): void
    {
        Schema::table('bitacora', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'persona_id']);
            $table->dropConstrainedForeignId('persona_id');
        });
    }
};
