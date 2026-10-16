<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Support\Region;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre', 'codigo', 'pais', 'zona_horaria', 'idioma',
        'alerta_stock_dias', 'alerta_mortalidad_porcentaje', 'alerta_mortalidad_dias', 'alerta_despacho_dias',
        'peso_canasta_kg', 'peso_canastilla_kg', 'unidad_peso_muestreo', 'formato_hora',
    ];

    protected function casts(): array
    {
        return [
            'suspendida_at' => InstanteLocal::class,
            // Primer uso (App\Support\PrimerUso)
            'ajustes_revisados_at' => InstanteLocal::class,
            'especies_revisadas_at' => InstanteLocal::class,
            'pesos_definidos_at' => InstanteLocal::class,
            'primer_uso_oculto_at' => InstanteLocal::class,
            'primer_uso_completado_at' => InstanteLocal::class,
            // Eliminación de la finca (App\Support\Datos\EliminadorFinca)
            'eliminacion_solicitada_at' => InstanteLocal::class,
            'eliminacion_programada_at' => InstanteLocal::class,
            'suspendida_por_eliminacion' => 'boolean',
            'alerta_stock_dias' => 'integer',
            'peso_canasta_kg' => 'float',
            'peso_canastilla_kg' => 'float',
            'alerta_mortalidad_porcentaje' => 'float',
            'alerta_mortalidad_dias' => 'integer',
            'alerta_despacho_dias' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // La región en memoria debe reflejar siempre los ajustes guardados
        static::saved(fn () => Region::olvidar());
        // Toda finca nueva arranca con las 6 especies por defecto, las 4 fases y las tablas de ración iniciales
        static::created(function (Tenant $finca) {
            Especie::cargarPorDefecto($finca->id);
            Fase::cargarPorDefecto($finca->id);
            TablaRacion::cargarPorDefecto($finca->id);
        });
    }

    /**
     * La finca de un código, sin importar mayúsculas ni espacios al inicio y al final (el personal lo escribe a mano).
     * Si dos fincas viejas tuvieran el mismo código con distintas mayúsculas, no se adivina: devuelve null.
     */
    public static function porCodigo(?string $codigo): ?self
    {
        $limpio = mb_strtolower(trim((string) $codigo));
        if ($limpio === '') {
            return null;
        }

        $fincas = self::query()->whereRaw('LOWER(codigo) = ?', [$limpio])->limit(2)->get();

        return $fincas->count() === 1 ? $fincas->first() : null;
    }

    /** Si un código escrito a mano es el de esta finca (sin importar mayúsculas ni espacios al inicio y al final). */
    public function tieneCodigo(?string $codigo): bool
    {
        return mb_strtolower(trim((string) $codigo)) === mb_strtolower($this->codigo);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function estaSuspendida(): bool
    {
        return $this->suspendida_at !== null;
    }

    /** Pidió su eliminación y está en el periodo de gracia (suspendida; solo el propietario entra a cancelarla). */
    public function enEliminacion(): bool
    {
        return $this->eliminacion_programada_at !== null;
    }
}
