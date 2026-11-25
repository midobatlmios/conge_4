<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Rules\MoroccanRib;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeProfile>
 */
class EmployeeProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'gender' => fake()->randomElement(Gender::cases()),
            'birth_date' => fake()->dateTimeBetween('-55 years', '-22 years')->format('Y-m-d'),
            'birth_place' => fake()->randomElement(['Casablanca', 'Rabat', 'Fès', 'Marrakech', 'Tanger', 'Agadir']),
            'cin' => fake()->unique()->regexify('[A-Z]{2}[0-9]{6}'),
            'cnss_number' => fake()->numerify('#########'),
            'marital_status' => fake()->randomElement(MaritalStatus::cases()),
            'children_count' => fake()->numberBetween(0, 4),
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Casablanca', 'Rabat', 'Fès', 'Marrakech', 'Tanger', 'Agadir']),
            'personal_phone' => '06'.fake()->numerify('########'),
            'personal_email' => fake()->unique()->safeEmail(),
            'bank_name' => fake()->randomElement(['Attijariwafa bank', 'Banque Populaire', 'CIH Bank', 'Bank of Africa']),
            'rib' => self::rib(),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_relation' => fake()->randomElement(['Conjoint(e)', 'Père', 'Mère', 'Frère', 'Sœur']),
            'emergency_contact_phone' => '06'.fake()->numerify('########'),
        ];
    }

    /** A random 24-digit Moroccan RIB with a valid key. */
    public static function rib(): string
    {
        $base = fake()->numerify(str_repeat('#', 22));
        $key = 97 - MoroccanRib::mod97($base.'00');

        return $base.str_pad((string) $key, 2, '0', STR_PAD_LEFT);
    }
}
