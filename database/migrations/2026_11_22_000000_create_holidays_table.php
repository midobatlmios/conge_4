<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Public holidays, managed by HR in the admin UI (they used to live in
 * config/conge.php). Dated before the 2026_11_23 migration because that one
 * counts working days, which needs this table on a fresh install.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date');
            // true: same day every year (only month and day matter);
            // false: that exact date only (Eid, Mawlid… move every year).
            $table->boolean('recurring')->default(false);
            $table->timestamps();
        });

        // Moroccan fixed public holidays (previously in config/conge.php).
        // Stored in leap year 2000 so any month-day is valid.
        $fixed = [
            '01-01' => 'Nouvel An',
            '01-11' => "Manifeste de l'Indépendance",
            '01-14' => 'Nouvel An amazigh',
            '05-01' => 'Fête du Travail',
            '07-30' => 'Fête du Trône',
            '08-14' => 'Allégeance Oued Eddahab',
            '08-20' => 'Révolution du Roi et du Peuple',
            '08-21' => 'Fête de la Jeunesse',
            '11-06' => 'Marche Verte',
            '11-18' => "Fête de l'Indépendance",
        ];

        DB::table('holidays')->insert(array_map(fn (string $monthDay, string $name) => [
            'name' => $name,
            'date' => "2000-{$monthDay}",
            'recurring' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], array_keys($fixed), $fixed));
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
