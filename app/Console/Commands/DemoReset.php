<?php

namespace App\Console\Commands;

use App\Models\CompanySetting;
use App\Models\DemandeDocument;
use App\Models\EmployeeDocument;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Wipes the database and its uploaded files, then loads the demo company
 * (DemoSeeder). Never runs in production.
 */
class DemoReset extends Command
{
    protected $signature = 'demo:reset {--force : Do not ask for confirmation}';

    protected $description = 'Erase all data and load the "Atlas Distribution" demo company';

    public function handle(): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('demo:reset ne s\'exécute jamais en production : il efface toutes les données.');

            return self::FAILURE;
        }

        $database = config('database.connections.'.config('database.default').'.database');

        if (! $this->option('force') && ! $this->confirm("Effacer toutes les données de « {$database} » et charger la démo ?")) {
            return self::FAILURE;
        }

        $this->call('migrate:fresh', ['--force' => true]);

        // Uploaded files belong to the records just erased.
        foreach ([[DemandeDocument::DISK, 'documents'], [EmployeeDocument::DISK, 'employee-documents'], [CompanySetting::DISK, 'company']] as [$disk, $directory]) {
            Storage::disk($disk)->deleteDirectory($directory);
        }

        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $this->newLine();
        $this->info('Démo chargée : Atlas Distribution. Mot de passe de tous les comptes : '.DemoSeeder::PASSWORD);
        $this->table(['Rôle', 'E-mail'], [
            ['Administrateur', 'admin@'.DemoSeeder::DOMAIN],
            ['RH', 'rh@'.DemoSeeder::DOMAIN],
            ['Paie (lecture seule)', 'paie@'.DemoSeeder::DOMAIN],
            ['Responsable d\'équipe', 'manager@'.DemoSeeder::DOMAIN],
            ['Employé', 'employe@'.DemoSeeder::DOMAIN],
        ]);

        return self::SUCCESS;
    }
}
