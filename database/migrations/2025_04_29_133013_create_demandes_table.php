<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('demandes', function (Blueprint $table) {
            $table->id();

            // Clé étrangère vers users
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Champs de la demande
            $table->date('date_debut')->nullable(); // Peut être générée automatiquement
            $table->date('date_fin')->nullable();   // Peut être générée automatiquement
            $table->integer('nbr_jours');
            $table->year('annee');
            $table->date('date_demande')->default(now());
            $table->enum('etat', ['en attente', 'acceptée', 'refusée'])->default('en attente');
            $table->foreignId('valide_par')->nullable()->constrained('users')->onDelete('set null'); // Admin qui valide/refuse

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
};
