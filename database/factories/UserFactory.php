<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Default to a standalone tenant so User::factory()->create() keeps
            // behaving like "one account, owns its own data" for every existing
            // test that doesn't care about roles — the DB column itself defaults
            // to 'user' as a safety net for stray raw inserts, but that's a
            // different concern from what the factory should produce by default.
            'role' => User::ROLE_TENANT,
            'tenant_id' => null,
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

    public function super(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_SUPER, 'tenant_id' => null]);
    }

    public function tenant(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_TENANT, 'tenant_id' => null]);
    }

    public function teamMemberOf(User $tenant): static
    {
        return $this->state(fn () => ['role' => User::ROLE_USER, 'tenant_id' => $tenant->id]);
    }
}
