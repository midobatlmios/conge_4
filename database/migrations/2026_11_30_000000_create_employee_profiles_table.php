<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal and administrative data of an employee (HR only; the employee can
 * read their own). Kept out of the users table so it is never sent to the
 * browser with a user by accident. Identity, bank and contact details are
 * encrypted with APP_KEY (text columns); cin_hash is a keyed hash of the CIN so
 * duplicates can still be detected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Identity
            $table->string('gender', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->text('cin')->nullable();
            $table->string('cin_hash', 64)->nullable()->unique();
            $table->text('cnss_number')->nullable();

            // Family situation
            $table->string('marital_status', 20)->nullable();
            $table->unsignedTinyInteger('children_count')->nullable();

            // Contact
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->text('personal_phone')->nullable();
            $table->text('personal_email')->nullable();

            // Bank
            $table->string('bank_name', 100)->nullable();
            $table->text('rib')->nullable();

            // Emergency contact
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_relation', 50)->nullable();
            $table->text('emergency_contact_phone')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
