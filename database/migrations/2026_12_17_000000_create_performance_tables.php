<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Annual reviews: one campaign a year (opened by HR), one review per employee
 * (self-evaluation → manager → employee comment → HR closes), objectives set in
 * a review for the next year and rated in that year's review, and the
 * manager's promotion / raise recommendation, which HR may turn into a career change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->date('due_on')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The reviewer when the campaign opened (null: HR reviews).
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            // self → manager → comment → hr → closed
            $table->string('status', 20)->default('self');
            $table->string('self_rating', 20)->nullable();
            $table->text('self_assessment')->nullable();
            $table->text('self_wishes')->nullable();
            $table->string('overall_rating', 20)->nullable();
            $table->text('manager_feedback')->nullable();
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('training_needs')->nullable();
            $table->date('interview_on')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('employee_comment')->nullable();
            // Recommendation: none, promotion or raise.
            $table->string('recommendation', 20)->nullable();
            $table->string('recommended_job_title', 100)->nullable();
            $table->text('recommended_salary')->nullable();
            $table->text('recommendation_reason')->nullable();
            // pending → applied (career change recorded) or declined by HR.
            $table->string('recommendation_status', 20)->nullable();
            $table->foreignId('career_change_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['review_campaign_id', 'user_id']);
        });

        Schema::create('objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The year the objective is for, and the review that set it.
            $table->unsignedSmallInteger('year');
            $table->foreignId('set_in_review_id')->nullable()->constrained('reviews')->nullOnDelete();
            $table->string('title', 150);
            $table->text('target')->nullable();
            $table->unsignedTinyInteger('weight')->default(0);
            $table->string('rating', 20)->nullable();
            $table->text('rating_comment')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('objectives');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('review_campaigns');
    }
};
