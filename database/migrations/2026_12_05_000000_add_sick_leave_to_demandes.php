<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sick leave (congé maladie): a new leave type, and the day HR filed the
 * CNSS dossier (needed from 4 calendar days, within 30 days).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->enum('type_conge', ['annuel', 'mariage', 'naissance', 'deces', 'sans_solde', 'recuperation', 'maladie'])->change();
            $table->date('cnss_filed_on')->nullable()->after('manager_comment');
        });
    }

    /** Fails if a request is a sick leave; change or delete those first. */
    public function down(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->dropColumn('cnss_filed_on');
            $table->enum('type_conge', ['annuel', 'mariage', 'naissance', 'deces', 'sans_solde', 'recuperation'])->change();
        });
    }
};
