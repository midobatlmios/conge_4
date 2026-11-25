<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finer roles: "rh" (day-to-day HR) and "paie" (read-only payroll) next to
 * "admin" and "user". Existing admins stay admins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'rh', 'paie', 'user'])->default('user')->change();
        });
    }

    /** Fails if an account has one of the new roles; change it first. */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'user'])->default('user')->change();
        });
    }
};
