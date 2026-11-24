<?php

use App\Services\LeaveCalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the "annuel" leave type and recount existing requests in working days.
     */
    public function up(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->enum('type_conge', ['annuel', 'mariage', 'naissance', 'deces', 'sans_solde', 'recuperation'])
                ->default('annuel')
                ->change();
        });

        DB::table('demandes')->orderBy('id')->each(function ($row) {
            DB::table('demandes')->where('id', $row->id)->update([
                'nbr_jours' => app(LeaveCalculator::class)->workingDays($row->date_debut, $row->date_fin),
            ]);
        });
    }

    /**
     * Fails if any "annuel" requests exist; change or delete them first.
     */
    public function down(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->enum('type_conge', ['mariage', 'naissance', 'deces', 'sans_solde', 'recuperation'])->change();
        });
    }
};
