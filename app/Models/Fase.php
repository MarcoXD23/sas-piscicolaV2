<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use App\Support\TenantActual;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fase productiva de un lago dentro de una finca (por ejemplo: Alevinaje, Precría, Levante, Engorde).
 * Cada finca arranca con las 4 fases de referencia y sus reglas iniciales por especie, editables en Ajustes › Fases.
 */
class Fase extends Model
{
    use PerteneceATenant;

    protected $fillable = ['clave', 'nombre', 'orden'];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    /** Fases iniciales con las que arranca una finca (valores de punto de partida de la finca piloto). */
    public const POR_DEFECTO = [
        ['clave' => 'alevinaje', 'orden' => 1],
        ['clave' => 'precria', 'orden' => 2],
        ['clave' => 'levante', 'orden' => 3],
        ['clave' => 'engorde', 'orden' => 4],
    ];

    /**
     * Reglas iniciales de referencia por fase (claves de especie => valores).
     * Punto de partida configurable por cada finca (tema 15.1 de dia-de-proceso.md).
     */
    private const REGLAS_POR_DEFECTO = [
        'alevinaje' => [
            '*' => ['peso_entrada_g' => 0.5, 'peso_salida_g' => 5.0, 'dias_esperados' => 30, 'supervivencia_porcentaje' => 85.0, 'densidad_min_m2' => 80.0, 'densidad_max_m2' => 150.0],
        ],
        'precria' => [
            '*' => ['peso_entrada_g' => 5.0, 'peso_salida_g' => 30.0, 'dias_esperados' => 60, 'supervivencia_porcentaje' => 90.0, 'densidad_min_m2' => 40.0, 'densidad_max_m2' => 80.0],
        ],
        'levante' => [
            '*' => ['peso_entrada_g' => 30.0, 'peso_salida_g' => 150.0, 'dias_esperados' => 90, 'supervivencia_porcentaje' => 92.0, 'densidad_min_m2' => 25.0, 'densidad_max_m2' => 40.0],
        ],
        'engorde' => [
            'cachama blanca' => ['peso_entrada_g' => 150.0, 'peso_salida_g' => 850.0, 'dias_esperados' => 90, 'supervivencia_porcentaje' => 95.0, 'densidad_min_m2' => 15.0, 'densidad_max_m2' => 24.0],
            'mojarra roja' => ['peso_entrada_g' => 150.0, 'peso_salida_g' => 600.0, 'dias_esperados' => 90, 'supervivencia_porcentaje' => 95.0, 'densidad_min_m2' => 15.0, 'densidad_max_m2' => 24.0],
            'mojarra negra' => ['peso_entrada_g' => 150.0, 'peso_salida_g' => 600.0, 'dias_esperados' => 90, 'supervivencia_porcentaje' => 95.0, 'densidad_min_m2' => 15.0, 'densidad_max_m2' => 24.0],
            'trucha arcoiris' => ['peso_entrada_g' => 80.0, 'peso_salida_g' => 400.0, 'dias_esperados' => 100, 'supervivencia_porcentaje' => 95.0, 'densidad_min_m2' => 12.0, 'densidad_max_m2' => 25.0],
            '*' => ['peso_entrada_g' => 150.0, 'peso_salida_g' => 600.0, 'dias_esperados' => 90, 'supervivencia_porcentaje' => 95.0, 'densidad_min_m2' => 15.0, 'densidad_max_m2' => 24.0],
        ],
    ];

    /**
     * Carga las fases y reglas iniciales en una finca de forma idempotente (sin duplicar si ya existen).
     */
    public static function cargarPorDefecto(int $tenantId): void
    {
        TenantActual::sinFiltro(function () use ($tenantId) {
            $especies = Especie::withoutGlobalScopes()->where('tenant_id', $tenantId)->get();

            foreach (self::POR_DEFECTO as $definicion) {
                $clave = $definicion['clave'];
                $nombre = __('fases.por_defecto.'.$clave);

                $fase = self::withoutGlobalScopes()
                    ->where(['tenant_id' => $tenantId, 'nombre' => $nombre])
                    ->first();

                if ($fase === null) {
                    $fase = (new self(['clave' => $clave, 'nombre' => $nombre, 'orden' => $definicion['orden']]))
                        ->forceFill(['tenant_id' => $tenantId]);
                    $fase->save();
                }

                $mapaReglas = self::REGLAS_POR_DEFECTO[$clave] ?? [];
                foreach ($especies as $especie) {
                    $valores = $mapaReglas[$especie->clave] ?? $mapaReglas['*'] ?? null;
                    if ($valores === null) {
                        continue;
                    }

                    $existe = ReglaFase::withoutGlobalScopes()
                        ->where([
                            'tenant_id' => $tenantId,
                            'fase_id' => $fase->id,
                            'especie_id' => $especie->id,
                        ])
                        ->exists();

                    if (! $existe) {
                        (new ReglaFase(['fase_id' => $fase->id, 'especie_id' => $especie->id] + $valores))
                            ->forceFill(['tenant_id' => $tenantId])
                            ->save();
                    }
                }
            }
        });
    }

    public function lagos(): HasMany
    {
        return $this->hasMany(Lago::class);
    }

    public function reglas(): HasMany
    {
        return $this->hasMany(ReglaFase::class);
    }
}
