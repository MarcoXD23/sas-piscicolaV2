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

    /** Fases iniciales con las que arranca una finca (las de la finca piloto; cada finca las cambia en Ajustes › Fases). */
    public const POR_DEFECTO = [
        ['clave' => 'alevinaje', 'orden' => 1],
        ['clave' => 'precria', 'orden' => 2],
        ['clave' => 'levante', 'orden' => 3],
        ['clave' => 'engorde', 'orden' => 4],
    ];

    /**
     * Reglas iniciales: solo los datos que dio la finca piloto (diseño, tema 15.1); lo demás queda vacío y la pantalla
     * dice «sin dato». Se aplican a las especies de agua cálida por defecto; la trucha y el alevinaje quedan sin reglas.
     * La migración de F2 copia estos mismos valores para las fincas que ya existían.
     */
    public const ESPECIES_CON_REGLAS = ['mojarra roja', 'mojarra negra', 'cachama blanca', 'bocachico', 'bagre rayado'];

    private const REGLAS_POR_DEFECTO = [
        'precria' => ['peso_salida_g' => 30.0, 'dias_esperados' => 60],
        'levante' => ['peso_entrada_g' => 30.0, 'peso_salida_g' => 150.0, 'dias_esperados' => 90],
        'engorde' => ['peso_entrada_g' => 150.0, 'dias_esperados' => 90, 'densidad_min_m2' => 15.0, 'densidad_max_m2' => 24.0],
    ];

    /** Peso de salida del engorde por especie (850 g la cachama y 600 g la tilapia); las demás, sin dato. */
    private const SALIDA_ENGORDE = ['cachama blanca' => 850.0, 'mojarra roja' => 600.0, 'mojarra negra' => 600.0];

    /** @return ?array<string, float|int> valores iniciales de la regla de una fase para una especie, o null si no tiene */
    public static function reglaPorDefecto(string $claveFase, ?string $claveEspecie): ?array
    {
        if (! isset(self::REGLAS_POR_DEFECTO[$claveFase]) || ! in_array($claveEspecie, self::ESPECIES_CON_REGLAS, true)) {
            return null;
        }

        return self::REGLAS_POR_DEFECTO[$claveFase]
            + ($claveFase === 'engorde' && isset(self::SALIDA_ENGORDE[$claveEspecie]) ? ['peso_salida_g' => self::SALIDA_ENGORDE[$claveEspecie]] : []);
    }

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

                foreach ($especies as $especie) {
                    $valores = self::reglaPorDefecto($clave, $especie->clave);
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
