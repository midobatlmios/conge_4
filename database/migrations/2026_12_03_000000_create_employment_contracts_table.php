<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employment contracts, kept as a history per employee (CDD, renewal, CDI…).
 * HR is alerted before a fixed-term contract or a trial period ends.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('category', 20)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('trial_end')->nullable();
            $table->boolean('trial_renewed')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_contracts');
    }
};
