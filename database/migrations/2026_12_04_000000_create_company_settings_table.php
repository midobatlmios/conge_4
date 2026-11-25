<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The company's details, printed on attestations: one row, edited by HR on
 * the "Entreprise" page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->nullable();
            $table->string('legal_form', 50)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('ice', 20)->nullable();
            $table->string('rc', 50)->nullable();
            $table->string('if_number', 30)->nullable();
            $table->string('cnss_number', 30)->nullable();
            $table->string('signatory_name', 150)->nullable();
            $table->string('signatory_title', 100)->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
