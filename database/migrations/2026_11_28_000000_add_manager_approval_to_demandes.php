<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-step approval: the employee's manager approves first ("validée manager",
 * then HR decides) or refuses, optionally with a reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->enum('etat', ['en attente', 'validée manager', 'acceptée', 'refusée', 'annulation demandée', 'annulée'])
                ->default('en attente')
                ->change();
            $table->text('manager_comment')->nullable()->after('comment');
        });
    }

    /** Fails if any request is "validée manager"; decide or reopen them first. */
    public function down(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->dropColumn('manager_comment');
            $table->enum('etat', ['en attente', 'acceptée', 'refusée', 'annulation demandée', 'annulée'])
                ->default('en attente')
                ->change();
        });
    }
};
