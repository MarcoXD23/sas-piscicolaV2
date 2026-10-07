<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use App\Support\Formato;
use App\Support\Region;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Registro de la bitácora: solo inserción. No se edita ni se borra (aquí, con un trigger y sin permisos en la base).
 * Se registra dentro de la misma transacción que la acción: si la acción falla, no queda registro.
 */
class Bitacora extends Model
{
    use PerteneceATenant;

    public const ACCIONES = ['racion', 'mortalidad', 'traslado', 'muestreo', 'ronda', 'novedad', 'cosecha', 'venta', 'despacho', 'recepcion', 'aviso_pesca', 'aviso_atendido', 'aprobacion', 'roles'];

    /** Roles de mayor a menor nivel: se guarda el más alto que tenga la persona entre los que permite la acción. */
    private const NIVEL_ROLES = [Rol::PROPIETARIO, Rol::ADMINISTRADOR, Rol::TRABAJADOR, Rol::CELADOR];

    protected $table = 'bitacora';

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'persona_id', 'rol', 'accion', 'lago_id', 'detalle', 'ocurrido_at', 'zona_horaria'];

    protected function casts(): array
    {
        return [
            'detalle' => 'array',
            'ocurrido_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException(__('bitacora.solo_insercion')));
        static::deleting(fn () => throw new LogicException(__('bitacora.solo_insercion')));
    }

    /**
     * @param  list<string>  $rolesPermitidos  Roles con los que se puede hacer la acción (los de la ruta)
     * @param  array<string, mixed>  $detalle  Datos de ese momento para mostrar después
     * @param  ?User  $persona  Persona afectada (por ejemplo, a quién se le cambiaron los roles)
     */
    public static function registrar(User $usuario, string $accion, ?Lago $lago, array $rolesPermitidos, array $detalle = [], ?User $persona = null): self
    {
        $roles = $usuario->roles()->pluck('nombre')->all();
        $rol = collect(self::NIVEL_ROLES)->first(fn ($nivel) => in_array($nivel, $rolesPermitidos, true) && in_array($nivel, $roles, true))
            ?? collect(self::NIVEL_ROLES)->first(fn ($nivel) => in_array($nivel, $roles, true));

        return self::create([
            'user_id' => $usuario->id,
            'persona_id' => $persona?->id,
            'rol' => $rol,
            'accion' => $accion,
            'lago_id' => $lago?->id,
            'detalle' => ($lago ? ['lago' => $lago->nombre] : []) + $detalle,
            'ocurrido_at' => now(),
            'zona_horaria' => Region::zona(),
        ]);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(User::class, 'persona_id');
    }

    public function lago(): BelongsTo
    {
        return $this->belongsTo(Lago::class);
    }

    /** Fecha y hora exactas en la zona que tenía la finca cuando ocurrió. */
    public function momentoLocal(): CarbonImmutable
    {
        return CarbonImmutable::instance($this->ocurrido_at)->setTimezone($this->zona_horaria);
    }

    /** Texto del detalle en el idioma de la finca. */
    public function textoDetalle(): string
    {
        $datos = collect($this->detalle)->map(fn ($valor, $clave) => match (true) {
            $clave === 'fecha' => CarbonImmutable::parse($valor)->format('d/m/Y'),
            is_float($valor) => rtrim(rtrim(Formato::numero($valor, 2), '0'), ',.'),
            is_int($valor) => Formato::numero($valor),
            default => (string) $valor,
        })->all();

        return __('bitacora.detalles.'.$this->accion, $datos);
    }
}
