<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Models\Concerns\PerteneceATenant;
use App\Models\Concerns\TieneRevision;
use App\Support\Region;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Permiso de ausencia solicitado por un trabajador o celador.
 */
class Permiso extends Model
{
    use PerteneceATenant, TieneRevision;

    protected $fillable = ['user_id', 'fecha_inicio', 'fecha_fin', 'motivo', 'estado', 'revisado_por', 'revisado_at', 'respuesta'];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'revisado_at' => InstanteLocal::class,
        ];
    }

    public function dias(): int
    {
        return (int) $this->fecha_inicio->diffInDays($this->fecha_fin) + 1;
    }

    /** Primer instante del permiso: medianoche del primer día, en la zona de la finca. */
    public function inicioLocal(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->fecha_inicio->toDateString(), Region::zona());
    }

    /** Instante en que termina el permiso: medianoche siguiente al último día, en la zona de la finca. */
    public function finLocal(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->fecha_fin->toDateString(), Region::zona())->addDay();
    }

    /** Turnos de la persona que caen dentro del permiso. */
    public function turnosAfectados()
    {
        return Turno::query()
            ->where('user_id', $this->user_id)
            ->queSeCruzanCon($this->inicioLocal(), $this->finLocal());
    }
}
