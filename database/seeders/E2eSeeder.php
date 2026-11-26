<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Accounts for the end-to-end tests (tests/e2e). Run on the throwaway e2e
 * database only: `php artisan migrate:fresh --seed --seeder=E2eSeeder --env=e2e`.
 * Every account uses the password "password".
 */
class E2eSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        $department = Department::create(['name' => 'Comptabilité']);

        $this->account('admin@e2e.test', 'Amal Admin', Role::Admin, ['hire_date' => '2012-01-02']);
        $this->account('rh@e2e.test', 'Rachid RH', Role::Hr, ['hire_date' => '2015-01-05']);
        $manager = $this->account('manager@e2e.test', 'Meryem Responsable', Role::User, [
            'hire_date' => '2018-03-01',
            'department_id' => $department->id,
            'job_title' => 'Cheffe comptable',
        ]);
        $this->account('employe@e2e.test', 'Elias Employé', Role::User, [
            'hire_date' => '2020-09-01',
            'department_id' => $department->id,
            'job_title' => 'Comptable',
            'manager_id' => $manager->id,
            'matricule' => 'E2E-001',
        ]);
    }

    /** @param array<string, mixed> $record */
    private function account(string $email, string $name, Role $role, array $record): User
    {
        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => self::PASSWORD, // hashed by the model's "hashed" cast
            'role' => $role,
            'email_verified_at' => now(),
            'privacy_acknowledged_at' => now(),
            // In the app since long ago, so last year's unused days carry over.
            'created_at' => now()->subYears(3),
            ...$record,
        ])->save();

        return $user;
    }
}
