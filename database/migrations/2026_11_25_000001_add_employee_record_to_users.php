<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee record: matricule, department, job title, hire date and manager.
 * All nullable in the database so existing accounts keep working; the hire
 * date is required by the admin forms (monthly accrual will be based on it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('matricule', 50)->nullable()->unique()->after('email');
            $table->foreignId('department_id')->nullable()->after('matricule')->constrained()->nullOnDelete();
            $table->string('job_title', 100)->nullable()->after('department_id');
            $table->date('hire_date')->nullable()->after('job_title');
            $table->foreignId('manager_id')->nullable()->after('hire_date')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['matricule', 'job_title', 'hire_date']);
        });
    }
};
