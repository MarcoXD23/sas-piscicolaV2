<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use App\Support\TenantActual;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tramo de la tabla de ración por finca y especie: desde/hasta qué peso en gramos aplica,
 * qué porcentaje de la biomasa dar al día, en cuántas tomas, con qué proteína/pellet y qué concentrado sugerir.
 */
class TablaRacion extends Model
{
    use PerteneceATenant;

    protected $table = 'tabla_racion';

    protected $fillable = [
        'especie_id',
        'peso_desde_g',
        'peso_hasta_g',
        'porcentaje_biomasa',
        'tomas_dia',
        'proteina_porcentaje',
        'pellet',
        'concentrado_id',
    ];

    protected function casts(): array
    {
        return [
            'peso_desde_g' => 'float',
            'peso_hasta_g' => 'float',
            'porcentaje_biomasa' => 'float',
            'tomas_dia' => 'integer',
            'proteina_porcentaje' => 'float',
        ];
    }

    /**
     * Tramos iniciales por perfil de alimentación: conservan los porcentajes y tomas de referencia
     * e incorporan proteína y tamaño de pellet orientativos (editables por cada finca).
     *
     * @var array<string, list<array{0: float, 1: float, 2: float, 3: int, 4: float, 5: string}>>
     */
    public const TRAMOS_POR_PERFIL = [
        'calido' => [
            [0.1, 5.0, 8.0, 5, 45.0, 'Harina'],
            [5.01, 20.0, 6.0, 4, 38.0, '1.5 mm'],
            [20.01, 50.0, 4.0, 4, 34.0, '2.5 mm'],
            [50.01, 100.0, 3.0, 3, 32.0, '3.5 mm'],
            [100.01, 200.0, 2.5, 3, 30.0, '4.5 mm'],
            [200.01, 300.0, 2.0, 3, 28.0, '4.5 mm'],
            [300.01, 500.0, 1.5, 2, 24.0, '6 mm'],
            [500.01, 99999.0, 1.2, 2, 24.0, '6 mm'],
        ],
        'detritivoro' => [
            [0.1, 5.0, 4.0, 4, 32.0, 'Harina'],
            [5.01, 20.0, 3.0, 3, 28.0, '1.5 mm'],
            [20.01, 50.0, 2.0, 3, 28.0, '2.5 mm'],
            [50.01, 100.0, 1.5, 2, 25.0, '3.5 mm'],
            [100.01, 200.0, 1.2, 2, 24.0, '4.5 mm'],
            [200.01, 300.0, 1.0, 2, 24.0, '4.5 mm'],
            [300.01, 500.0, 0.8, 2, 24.0, '4.5 mm'],
            [500.01, 99999.0, 0.6, 2, 24.0, '4.5 mm'],
        ],
        'frio' => [
            [0.1, 5.0, 5.0, 6, 48.0, 'Harina'],
            [5.01, 20.0, 3.5, 5, 45.0, '1.5 mm'],
            [20.01, 50.0, 2.5, 4, 45.0, '2.5 mm'],
            [50.01, 100.0, 2.0, 3, 42.0, '3.5 mm'],
            [100.01, 200.0, 1.6, 3, 42.0, '4.5 mm'],
            [200.01, 99999.0, 1.2, 2, 40.0, '6 mm'],
        ],
    ];

    public function especie(): BelongsTo
    {
        return $this->belongsTo(Especie::class);
    }

    public function concentrado(): BelongsTo
    {
        return $this->belongsTo(Concentrado::class);
    }

    /**
     * Carga de forma idempotente la tabla de ración inicial para todas las especies con perfil de alimentación de la finca.
     */
    public static function cargarPorDefecto(int $tenantId): void
    {
        TenantActual::sinFiltro(function () use ($tenantId) {
            $especies = Especie::query()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('alimentacion')
                ->get();

            foreach ($especies as $especie) {
                self::cargarParaEspecie($especie, reemplazar: false);
            }
        });
    }

    /**
     * Carga los tramos por defecto para una especie. Si $reemplazar es true, restaura la tabla de esa especie.
     */
    public static function cargarParaEspecie(Especie $especie, bool $reemplazar = false): void
    {
        $perfil = $especie->alimentacion;
        if ($perfil === null || ! isset(self::TRAMOS_POR_PERFIL[$perfil])) {
            return;
        }

        TenantActual::sinFiltro(function () use ($especie, $perfil, $reemplazar) {
            $tenantId = (int) $especie->tenant_id;

            if ($reemplazar) {
                self::query()
                    ->where('tenant_id', $tenantId)
                    ->where('especie_id', $especie->id)
                    ->delete();
            }

            foreach (self::TRAMOS_POR_PERFIL[$perfil] as [$desde, $hasta, $porcentaje, $tomas, $proteina, $pellet]) {
                $existente = self::query()
                    ->where('tenant_id', $tenantId)
                    ->where('especie_id', $especie->id)
                    ->where('peso_hasta_g', $hasta)
                    ->first();

                if ($existente === null) {
                    (new self([
                        'especie_id' => $especie->id,
                        'peso_desde_g' => $desde,
                        'peso_hasta_g' => $hasta,
                        'porcentaje_biomasa' => $porcentaje,
                        'tomas_dia' => $tomas,
                        'proteina_porcentaje' => $proteina,
                        'pellet' => $pellet,
                    ]))->forceFill(['tenant_id' => $tenantId])->save();
                }
            }
        });
    }
}

