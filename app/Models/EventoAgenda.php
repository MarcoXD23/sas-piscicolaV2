<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Models\Concerns\PerteneceATenant;
use App\Support\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoAgenda extends Model
{
    use PerteneceATenant;

    protected $table = 'eventos_agenda';

    /** Tipos de evento (el texto de cada uno está en lang/es/agenda.php, «tipos»). */
    public const TIPOS = ['cosecha', 'compra', 'visita', 'mantenimiento', 'pago', 'sanidad', 'otro'];

    /** @return array<string, string> código => texto en el idioma de la finca */
    public static function opciones(): array
    {
        return collect(self::TIPOS)->mapWithKeys(fn (string $codigo) => [$codigo => __('agenda.tipos.'.$codigo)])->all();
    }

    protected $fillable = [
        'creado_por', 'lago_id', 'titulo', 'tipo', 'inicia_at', 'termina_at',
        'todo_el_dia', 'recordar_dias_antes', 'descripcion', 'completado_at',
    ];

    protected function casts(): array
    {
        return [
            'inicia_at' => InstanteLocal::class,
            'termina_at' => InstanteLocal::class,
            'completado_at' => InstanteLocal::class,
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
        return in_array($this->tipo, self::TIPOS, true) ? __('agenda.tipos.'.$this->tipo) : $this->tipo;
    }

    /**
     * Eventos sin completar que hay que tener presentes hoy: los vencidos y los que están
     * dentro de su período de recordatorio (desde N días antes hasta el día del evento).
     */
    public function scopeParaRecordar(Builder $consulta): void
    {
        // Un evento vencido también cumple la condición (su fecha menos los días de aviso ya pasó)
        $consulta->whereNull('completado_at')
            ->whereRaw("((inicia_at AT TIME ZONE 'UTC') AT TIME ZONE ?)::date - recordar_dias_antes <= ?", [Region::zona(), Region::hoy()->toDateString()]);
    }

    public function estaVencido(): bool
    {
        return ! $this->estaCompletado() && $this->inicia_at->toDateString() < Region::hoy()->toDateString();
    }
}
