<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mortalidad extends Model
{
    use HasFactory, PerteneceATenant;

    protected $table = 'mortalidades';

    protected $fillable = [
        'siembra_id',
        'user_id',
        'fecha',
        'cantidad',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function siembra(): BelongsTo
    {
        return $this->belongsTo(Siembra::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
