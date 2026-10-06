<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\PerteneceATenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PerteneceATenant;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'aprobado_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_user')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    public function estaAprobado(): bool
    {
        return $this->aprobado_at !== null;
    }

    /**
     * ¿Tiene al menos uno de los roles indicados?
     */
    public function tieneRol(string ...$nombres): bool
    {
        return $this->roles->pluck('nombre')->intersect($nombres)->isNotEmpty();
    }

    /**
     * Reemplaza los roles del usuario por los indicados (guardando tenant_id en la pivote).
     *
     * @param  array<int, string>  $nombres
     */
    public function asignarRoles(array $nombres): void
    {
        $ids = Rol::query()->whereIn('nombre', $nombres)->pluck('id');

        $this->roles()->sync(
            $ids->mapWithKeys(fn (int $id) => [$id => ['tenant_id' => $this->tenant_id]])->all()
        );

        $this->unsetRelation('roles');
    }

    /** Personal de campo: personas aprobadas con rol de trabajador o celador. */
    public function scopeDeCampo(Builder $consulta): void
    {
        $consulta->whereNotNull('aprobado_at')
            ->whereHas('roles', fn ($roles) => $roles->whereIn('nombre', Turno::ROLES));
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class);
    }

    /** Turno en curso para el rol indicado, si lo hay. */
    public function turnoEnCurso(string $rol): ?Turno
    {
        return $this->turnos()->where('rol', $rol)->enCurso()->first();
    }

    /** Próximo turno (de cualquier rol) que aún no ha empezado. */
    public function proximoTurno(): ?Turno
    {
        return $this->turnos()->where('inicia_at', '>', now())->orderBy('inicia_at')->first();
    }

    /**
     * ¿Puede trabajar ahora como el rol indicado? Propietario y administrador no dependen de turnos;
     * trabajador y celador solo dentro de un turno en curso de ese rol.
     */
    public function puedeTrabajarComo(string $rol): bool
    {
        if ($this->tieneRol(Rol::PROPIETARIO, Rol::ADMINISTRADOR)) {
            return true;
        }

        return $this->tieneRol($rol) && $this->turnoEnCurso($rol) !== null;
    }
}
