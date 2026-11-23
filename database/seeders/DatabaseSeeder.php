<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with local development accounts.
     *
     * Safe to run more than once: existing accounts are left untouched.
     * Both accounts use the password "password" — development only.
     */
    public function run(): void
    {
        $this->createAccount('admin@example.com', 'Admin', 'admin');
        $this->createAccount('test@example.com', 'Test User', 'user');
    }

    private function createAccount(string $email, string $name, string $role): void
    {
        if (User::where('email', $email)->exists()) {
            return;
        }

        (new User)->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => 'password', // hashed by the model's "hashed" cast
            'role' => $role,
            'email_verified_at' => now(),
        ])->save();
    }
}
