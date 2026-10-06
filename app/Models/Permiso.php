<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use App\Models\Concerns\TieneRevision;
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
            'revisado_at' => 'datetime',
        ];
    }

    public function dias(): int
    {
        return (int) $this->fecha_inicio->diffInDays($this->fecha_fin) + 1;
    }

    /** Turnos de la persona que caen dentro del permiso. */
    public function turnosAfectados()
    {
        return Turno::query()
            ->where('user_id', $this->user_id)
            ->queSeCruzanCon($this->fecha_inicio->copy()->startOfDay(), $this->fecha_fin->copy()->addDay()->startOfDay());
    }
}
