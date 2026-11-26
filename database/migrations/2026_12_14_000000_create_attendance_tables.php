<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance: the company schedule (one for everyone), one clock-in/clock-out
 * record per employee and day, and corrections employees ask for (forgotten
 * or wrong clocking), approved by their manager or HR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('work_start', 5)->default('08:30');
            $table->string('lunch_start', 5)->default('12:30');
            $table->string('lunch_end', 5)->default('14:00');
            $table->string('work_end', 5)->default('18:00');
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(10);
            // Days before it are not counted (no absences before the module was used).
            $table->date('attendance_started_on')->nullable();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('clock_in', 5);
            $table->string('clock_out', 5)->nullable();
            // 'app' (the employee's button) or 'correction' (an approved correction).
            $table->string('source', 20)->default('app');
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index('date');
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('clock_in', 5);
            $table->string('clock_out', 5)->nullable();
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('refusal_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_records');
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['work_start', 'lunch_start', 'lunch_end', 'work_end', 'late_tolerance_minutes', 'attendance_started_on']);
        });
    }
};
