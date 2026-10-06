<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Models\Concerns\PerteneceATenant;
use App\Models\Concerns\TieneRevision;
use Illuminate\Database\Eloquent\Model;

class HoraExtra extends Model
{
    use PerteneceATenant, TieneRevision;

    protected $table = 'horas_extras';

    protected $fillable = ['user_id', 'fecha', 'horas', 'motivo', 'estado', 'revisado_por', 'revisado_at', 'respuesta'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'horas' => 'float',
            'revisado_at' => InstanteLocal::class,
        ];
    }
}
