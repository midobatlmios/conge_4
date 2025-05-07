<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDemandesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('demandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date_demande');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->integer('nbr_jours');
            $table->integer('annee');
            $table->enum('type_conge', ['mariage', 'naissance', 'deces', 'sans_solde', 'recuperation']);
            $table->enum('etat', ['en attente', 'acceptée', 'refusée'])->default('en attente');
            $table->foreignId('valide_par')->nullable()->constrained('users')->onDelete('set null');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demandes');
    }
} 