<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoAgenda extends Model
{
    use PerteneceATenant;

    protected $table = 'eventos_agenda';

    /** Tipos de evento: clave => texto. */
    public const TIPOS = [
        'cosecha' => 'Cosecha',
        'compra' => 'Compra',
        'visita' => 'Visita',
        'mantenimiento' => 'Mantenimiento',
        'pago' => 'Pago',
        'sanidad' => 'Sanidad',
        'otro' => 'Otro',
    ];

    protected $fillable = [
        'creado_por', 'lago_id', 'titulo', 'tipo', 'inicia_at', 'termina_at',
        'todo_el_dia', 'recordar_dias_antes', 'descripcion', 'completado_at',
    ];

    protected function casts(): array
    {
        return [
            'inicia_at' => 'datetime',
            'termina_at' => 'datetime',
            'completado_at' => 'datetime',
            'todo_el_dia' => 'boolean',
            'recordar_dias_antes' => 'integer',
        ];
    }

    public function lago(): BelongsTo
    {
        return $this->belongsTo(Lago::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function estaCompletado(): bool
    {
        return $this->completado_at !== null;
    }

    public function textoTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    /**
     * Eventos sin completar que hay que tener presentes hoy: los vencidos y los que están
     * dentro de su período de recordatorio (desde N días antes hasta el día del evento).
     */
    public function scopeParaRecordar(Builder $consulta): void
    {
        // Un evento vencido también cumple la condición (su fecha menos los días de aviso ya pasó)
        $consulta->whereNull('completado_at')
            ->whereRaw('inicia_at::date - recordar_dias_antes <= ?', [today()->toDateString()]);
    }

    public function estaVencido(): bool
    {
        return ! $this->estaCompletado() && $this->inicia_at->lt(today());
    }
}
