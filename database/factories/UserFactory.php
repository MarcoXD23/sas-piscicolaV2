<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Solo para pruebas. La contraseña por defecto es aleatoria.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Str::random(40),
            'aprobado_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Usuario registrado que aún no ha sido aprobado por el propietario.
     */
    public function pendiente(): static
    {
        return $this->state(fn (array $attributes) => [
            'aprobado_at' => null,
        ]);
    }

    /**
     * Asigna roles al usuario después de crearlo.
     */
    public function conRoles(string ...$roles): static
    {
        return $this->afterCreating(fn (User $user) => $user->asignarRoles($roles));
    }
}

