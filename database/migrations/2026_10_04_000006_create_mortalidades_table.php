<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mortalidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('siembra_id')->constrained('siembras')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            
            $table->date('fecha');
            $table->unsignedInteger('cantidad');
            
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('siembra_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mortalidades');
    }
};
