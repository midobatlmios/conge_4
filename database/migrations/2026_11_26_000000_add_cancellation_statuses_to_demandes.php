<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cancelling an accepted leave: the employee asks ("annulation demandée", the
 * days stay booked), an admin approves ("annulée", the days are returned).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->enum('etat', ['en attente', 'acceptée', 'refusée', 'annulation demandée', 'annulée'])
                ->default('en attente')
                ->change();
        });
    }

    /** Fails if any request has one of the new statuses; change or delete them first. */
    public function down(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->enum('etat', ['en attente', 'acceptée', 'refusée'])->default('en attente')->change();
        });
    }
};
