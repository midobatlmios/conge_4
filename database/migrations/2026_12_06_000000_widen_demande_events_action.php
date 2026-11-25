<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "cancellation_requested" (22 characters) did not fit in the 20-character
 * action column: on MySQL, asking to cancel an accepted leave failed.
 * (SQLite, used by the tests, does not enforce string lengths.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demande_events', function (Blueprint $table) {
            $table->string('action', 40)->change();
        });
    }

    public function down(): void
    {
        Schema::table('demande_events', function (Blueprint $table) {
            $table->string('action', 20)->change();
        });
    }
};
