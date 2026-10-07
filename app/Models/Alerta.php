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

    public const DESPACHO_PENDIENTE = 'despacho_pendiente';

    public const DESPACHO_DIFERENCIA = 'despacho_diferencia';

    public const TIPOS = [self::STOCK, self::MORTALIDAD, self::AGUA, self::DESPACHO_PENDIENTE, self::DESPACHO_DIFERENCIA];

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

    /**
     * Tipos que puede ver cada persona. La diferencia de un despacho es solo del propietario (él despacha);
     * el administrador recibe el concentrado, así que ve el stock y los despachos sin recibir.
     *
     * @return list<string>
     */
    public static function tiposVisiblesPara(User $usuario): array
    {
        return $usuario->tieneRol(Rol::PROPIETARIO)
            ? self::TIPOS
            : [self::STOCK, self::MORTALIDAD, self::AGUA, self::DESPACHO_PENDIENTE];
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
            self::DESPACHO_PENDIENTE => __('alertas.mensajes.despacho_pendiente', [
                'kg' => $decimal($d['kg']), 'concentrado' => $d['concentrado'], 'fecha' => $d['fecha'], 'proveedor' => $d['proveedor'], 'dias' => $d['dias'],
            ]),
            self::DESPACHO_DIFERENCIA => __($d['estado'] === DespachoAlimento::FALTANTE ? 'alertas.mensajes.despacho_faltante' : 'alertas.mensajes.despacho_sobrante', [
                'concentrado' => $d['concentrado'], 'fecha' => $d['fecha'], 'despachado' => $decimal($d['despachado']),
                'recibido' => $decimal($d['recibido']), 'diferencia' => $decimal(abs($d['diferencia'])),
            ]),
            default => $this->tipo,
        };
    }

    /** Pantalla donde se atiende la alerta. */
    public function enlace(): ?string
    {
        return match ($this->tipo) {
            self::STOCK, self::DESPACHO_PENDIENTE, self::DESPACHO_DIFERENCIA => route('admin.bodega.index'),
            self::MORTALIDAD, self::AGUA => isset($this->datos['lago_id']) ? route('admin.lagos.show', $this->datos['lago_id']) : null,
            default => null,
        };
    }
}
