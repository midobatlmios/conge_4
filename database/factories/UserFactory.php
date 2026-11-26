<?php

namespace Database\Factories;

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
            // Already accepted the data protection notice (see the unacknowledged state).
            'privacy_acknowledged_at' => now(),
            // 1 to 4 years of service: past the 6-month wait, no seniority bonus yet.
            // now() follows travelTo() in tests; Faker's relative dates use the real clock.
            'hire_date' => fake()->dateTimeBetween(now()->subYears(4), now()->subYear())->format('Y-m-d'),
        ];
    }

    /** Has not yet read and accepted the data protection notice. */
    public function privacyNotAcknowledged(): static
    {
        return $this->state(fn () => ['privacy_acknowledged_at' => null]);
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

    /** An employee who has left: cannot sign in, history kept. */
    public function deactivated(): static
    {
        return $this->state(fn (array $attributes) => [
            'deactivated_at' => now(),
        ]);
    }
}
