<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tarea extends Model
{
    use PerteneceATenant;

    protected $fillable = ['asignada_a', 'creada_por', 'lago_id', 'titulo', 'descripcion', 'fecha', 'completada_at', 'nota_cierre'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'completada_at' => 'datetime',
        ];
    }

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignada_a');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por');
    }

    public function lago(): BelongsTo
    {
        return $this->belongsTo(Lago::class);
    }

    public function scopePendientes(Builder $consulta): void
    {
        $consulta->whereNull('completada_at');
    }

    public function estaCompletada(): bool
    {
        return $this->completada_at !== null;
    }

    public function estaVencida(): bool
    {
        return ! $this->estaCompletada() && $this->fecha->lt(today());
    }
}
