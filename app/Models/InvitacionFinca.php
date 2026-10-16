<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Invitación para crear una finca (panel de plataforma): un correo, un enlace de un solo uso y 7 días para usarlo.
 * Es de la plataforma, no de una finca: sin tenant_id ni filtro de finca.
 * El código del enlace solo existe en el enlace; aquí se guarda su sha256.
 */
class InvitacionFinca extends Model
{
    protected $table = 'invitaciones_finca';

    public const DIAS_VIGENCIA = 7;

    public const PENDIENTE = 'pendiente';

    public const USADA = 'usada';

    public const VENCIDA = 'vencida';

    public const ANULADA = 'anulada';

    protected $fillable = ['correo'];

    protected function casts(): array
    {
        return [
            'vence_at' => 'datetime',
            'usada_at' => 'datetime',
            'anulada_at' => 'datetime',
        ];
    }

    /**
     * Crea la invitación y devuelve [invitación, código del enlace]. El código no se guarda: solo se muestra esta vez.
     *
     * @return array{0: self, 1: string}
     */
    public static function crear(string $correo, ?AdministradorPlataforma $admin): array
    {
        $codigo = Str::random(48);
        $invitacion = (new self(['correo' => mb_strtolower(trim($correo))]))->forceFill([
            'codigo_hash' => self::hash($codigo),
            'vence_at' => now()->addDays(self::DIAS_VIGENCIA),
            'creada_por' => $admin?->id,
        ]);
        $invitacion->save();

        return [$invitacion, $codigo];
    }

    /** La invitación de un código, solo si todavía se puede usar (sin decir por qué no, si no se puede). */
    public static function vigente(string $codigo, bool $bloquear = false): ?self
    {
        $invitacion = self::query()->where('codigo_hash', self::hash($codigo))->when($bloquear, fn ($consulta) => $consulta->lockForUpdate())->first();

        return $invitacion?->estado() === self::PENDIENTE ? $invitacion : null;
    }

    public static function hash(string $codigo): string
    {
        return hash('sha256', $codigo);
    }

    public function estado(): string
    {
        return match (true) {
            $this->usada_at !== null => self::USADA,
            $this->anulada_at !== null => self::ANULADA,
            $this->vence_at->isPast() => self::VENCIDA,
            default => self::PENDIENTE,
        };
    }

    public function fincaCreada(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'finca_creada_id');
    }
}
