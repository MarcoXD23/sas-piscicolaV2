<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use App\Support\Formato;
use App\Support\MomentoRegistro;
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

    public const ACCIONES = ['racion', 'mortalidad', 'traslado', 'muestreo', 'ronda', 'novedad', 'cosecha', 'venta', 'despacho', 'recepcion', 'aviso_pesca', 'aviso_atendido', 'medicion', 'aprobacion', 'roles', 'dos_pasos', 'exportacion', 'eliminacion', 'eliminacion_anulada',
        'jornalero_creado', 'jornalero_editado', 'jornalero_activado', 'jornalero_inactivo', 'jornalero_anonimo'];

    /** Roles de mayor a menor nivel: se guarda el más alto que tenga la persona entre los que permite la acción. */
    private const NIVEL_ROLES = [Rol::PROPIETARIO, Rol::ADMINISTRADOR, Rol::TRABAJADOR, Rol::CELADOR];

    /** Datos que se agregaron después a una acción: los registros viejos no los tienen. */
    private const DATOS_NUEVOS = ['cosecha' => ['destino']];

    protected $table = 'bitacora';

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'persona_id', 'tercero_id', 'rol', 'accion', 'lago_id', 'detalle', 'ocurrido_at', 'zona_horaria'];

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
     * @param  ?Tercero  $tercero  Persona sin cuenta afectada (jornalero): se guarda solo su id, nunca su nombre ni su
     *                             documento; el nombre se resuelve al mostrarlo (así la anonimización también lo cubre)
     */
    public static function registrar(User $usuario, string $accion, ?Lago $lago, array $rolesPermitidos, array $detalle = [], ?User $persona = null, ?Tercero $tercero = null): self
    {
        $roles = $usuario->roles()->pluck('nombre')->all();
        $rol = collect(self::NIVEL_ROLES)->first(fn ($nivel) => in_array($nivel, $rolesPermitidos, true) && in_array($nivel, $roles, true))
            ?? collect(self::NIVEL_ROLES)->first(fn ($nivel) => in_array($nivel, $roles, true));

        // Un registro hecho sin conexión guarda la hora real en que ocurrió y cuándo llegó
        $momento = app(MomentoRegistro::class);
        if ($momento->sinConexion()) {
            $detalle += ['sin_conexion' => true, 'recibido_at' => now()->utc()->toIso8601String()];
            // El reloj del teléfono estaba mal puesto: la hora ya viene corregida y se deja constancia
            if ($momento->minutosCorregidos() !== null) {
                $detalle += ['reloj_corregido_min' => $momento->minutosCorregidos()];
            }
        }

        return self::create([
            'user_id' => $usuario->id,
            'persona_id' => $persona?->id,
            'tercero_id' => $tercero?->id,
            'rol' => $rol,
            'accion' => $accion,
            'lago_id' => $lago?->id,
            'detalle' => ($lago ? ['lago' => $lago->nombre] : []) + $detalle,
            'ocurrido_at' => $momento->ahora(),
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

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class);
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

        // Persona sin cuenta: el nombre de hoy (o «Jornalero N» si se anonimizó), nunca uno guardado en el detalle
        if ($this->tercero_id !== null) {
            $nombre = $this->tercero?->nombreCompleto() ?? '';
            $datos['tercero'] = $nombre;
            $datos['cliente'] ??= $nombre;
        }

        // Registros anteriores a un dato nuevo (por ejemplo, cosechas sin destino) se muestran con "—"
        $datos += collect(self::DATOS_NUEVOS[$this->accion] ?? [])->mapWithKeys(fn ($clave) => [$clave => __('cosechas.sin_dato')])->all();
        $texto = __('bitacora.detalles.'.$this->accion, $datos);

        if (! empty($this->detalle['sin_conexion']) && isset($this->detalle['recibido_at'])) {
            $llegada = CarbonImmutable::parse($this->detalle['recibido_at'])->setTimezone($this->zona_horaria)->format('d/m/Y H:i');
            $texto .= ' · '.__('bitacora.sin_conexion', ['llegada' => $llegada]);
        }

        if (isset($this->detalle['reloj_corregido_min'])) {
            $texto .= ' · '.__('bitacora.reloj_corregido', ['minutos' => Formato::numero((int) $this->detalle['reloj_corregido_min'])]);
        }

        return $texto;
    }
}
