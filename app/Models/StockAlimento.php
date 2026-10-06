<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAlimento extends Model
{
    use HasFactory, PerteneceATenant;

    protected $table = 'stocks_alimento';

    protected $fillable = [
        'tipo_concentrado',
        'cantidad_kg',
    ];
}
