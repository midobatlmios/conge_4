<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment: job offers (published on the public careers page), candidates
 * (applications from that page or added by HR, with their CV) moving through
 * 6 stages, interviews with the hiring manager's evaluation, and the link to
 * the employee account created on hiring.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_offers', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('slug', 180)->unique();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location', 100)->nullable();
            $table->string('contract_type', 20);
            $table->text('description');
            $table->text('requirements')->nullable();
            $table->foreignId('hiring_manager_id')->nullable()->constrained('users')->nullOnDelete();
            // draft → published → closed
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->date('closes_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_offer_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('email', 150);
            $table->string('phone', 30)->nullable();
            $table->text('message')->nullable();
            // CV on the private disk, random file name.
            $table->string('cv_path')->nullable();
            $table->string('cv_original_name')->nullable();
            $table->string('cv_mime_type', 100)->nullable();
            // careers_page or manual
            $table->string('source', 20)->default('careers_page');
            // new → screening → interview → offer → hired / rejected
            $table->string('stage', 20)->default('new');
            $table->timestamp('stage_changed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('hired_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hired_at')->nullable();
            // Agreed to the processing of their data (loi 09-08) on the careers page.
            $table->timestamp('consent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['job_offer_id', 'stage']);
        });

        Schema::create('candidate_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->foreignId('interviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode', 100)->nullable();
            $table->string('rating', 20)->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_interviews');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('job_offers');
    }
};
