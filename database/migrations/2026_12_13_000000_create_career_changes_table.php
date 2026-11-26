<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Career history: promotions, raises, transfers. Each change keeps the values
 * before and after it (job title, department, category, gross salary), so the
 * employee record and the current contract can show the latest values while
 * the past stays readable (and payroll cost can be computed month by month).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The contract the category and salary changes apply to.
            $table->foreignId('contract_id')->nullable()->constrained('employment_contracts')->nullOnDelete();
            $table->date('effective_date');
            $table->string('kind', 20);
            $table->string('previous_job_title', 100)->nullable();
            $table->string('job_title', 100)->nullable();
            // Links to apply and undo the change, names so the history survives a
            // department being renamed or removed.
            $table->boolean('department_changed')->default(false);
            $table->foreignId('previous_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('previous_department', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('previous_category', 20)->nullable();
            $table->string('category', 20)->nullable();
            // Monthly gross salaries in MAD, encrypted like on the contract.
            $table->text('previous_gross_salary')->nullable();
            $table->text('gross_salary')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_changes');
    }
};
