<?php

use App\Support\CatalogoEspecies;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ficha de cultivo y de procesamiento de cada especie, más color y aspecto. Todo opcional y editable por el administrador.
 * Las especies por defecto (con clave) de cada finca reciben el contenido de referencia, marcado como orientativo;
 * las especies propias de la finca quedan vacías. No se tocan nombres, rangos ni siembras.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('especies', function (Blueprint $table) {
            $table->jsonb('cultivo')->nullable();          // preparación, siembra, alimentación, calidad del agua, cosecha, densidades, tipo de estanque
            $table->jsonb('procesamiento')->nullable();    // ayuno, sacrificio, eviscerado, fileteado, frío, empaque
            $table->string('color', 200)->nullable();
            $table->string('aspecto', 500)->nullable();
            $table->boolean('ficha_orientativa')->default(false);
        });

        foreach (DB::table('especies')->whereNotNull('clave')->get(['id', 'clave']) as $especie) {
            $ficha = CatalogoEspecies::fichaPorDefecto($especie->clave);
            DB::table('especies')->where('id', $especie->id)->update([
                'cultivo' => json_encode($ficha['cultivo'], JSON_UNESCAPED_UNICODE),
                'procesamiento' => json_encode($ficha['procesamiento'], JSON_UNESCAPED_UNICODE),
                'color' => $ficha['color'],
                'aspecto' => $ficha['aspecto'],
                'ficha_orientativa' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('especies', function (Blueprint $table) {
            $table->dropColumn(['cultivo', 'procesamiento', 'color', 'aspecto', 'ficha_orientativa']);
        });
    }
};
