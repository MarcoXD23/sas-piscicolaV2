<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Models\Concerns\PerteneceATenant;
use App\Support\Formato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Alerta de la finca (historial). Se abre cuando se cumple la condición y se cierra sola cuando deja de cumplirse.
 */
class Alerta extends Model
{
    use PerteneceATenant;

    public const STOCK = 'stock';

    public const MORTALIDAD = 'mortalidad';

    public const AGUA = 'agua';

    public const TIPOS = [self::STOCK, self::MORTALIDAD, self::AGUA];

    public const ADVERTENCIA = 'advertencia';

    public const CRITICA = 'critica';

    protected $fillable = ['tipo', 'referencia', 'severidad', 'datos', 'abierta_at', 'cerrada_at', 'notificada_at'];

    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'abierta_at' => InstanteLocal::class,
            'cerrada_at' => InstanteLocal::class,
            'notificada_at' => InstanteLocal::class,
        ];
    }

    public function scopeAbiertas(Builder $consulta): void
    {
        $consulta->whereNull('cerrada_at');
    }

    /** Tipos que puede ver cada persona: la bodega (stock) es solo del propietario. @return list<string> */
    public static function tiposVisiblesPara(User $usuario): array
    {
        return $usuario->tieneRol(Rol::PROPIETARIO) ? self::TIPOS : [self::MORTALIDAD, self::AGUA];
    }

    public function esCritica(): bool
    {
        return $this->severidad === self::CRITICA;
    }

    public function estaAbierta(): bool
    {
        return $this->cerrada_at === null;
    }

    /** Texto de la alerta en el idioma de la finca. */
    public function mensaje(): string
    {
        $d = $this->datos;
        // Un decimal, sin ",0" cuando es entero (5 días, 3,5 %)
        $decimal = fn ($valor) => rtrim(rtrim(Formato::numero((float) $valor, 1), '0'), ',.');

        return match ($this->tipo) {
            self::STOCK => ($d['agotado'] ?? false)
                ? __('alertas.mensajes.stock_agotado', ['concentrado' => $d['concentrado']])
                : __('alertas.mensajes.stock_bajo', ['concentrado' => $d['concentrado'], 'dias' => $decimal($d['dias']), 'minimo' => $d['minimo']]),
            self::MORTALIDAD => __('alertas.mensajes.mortalidad', [
                'lago' => $d['lago'], 'muertos' => Formato::numero($d['muertos']), 'porcentaje' => $decimal($d['porcentaje']),
                'dias' => $d['dias'], 'umbral' => $decimal($d['umbral']),
            ]),
            self::AGUA => __('alertas.mensajes.agua', ['lago' => $d['lago'], 'detalle' => implode(' ', $d['problemas'])]),
            default => $this->tipo,
        };
    }

    /** Pantalla donde se atiende la alerta. */
    public function enlace(): ?string
    {
        return match ($this->tipo) {
            self::STOCK => route('admin.bodega.index'),
            self::MORTALIDAD, self::AGUA => isset($this->datos['lago_id']) ? route('admin.lagos.show', $this->datos['lago_id']) : null,
            default => null,
        };
    }
}
