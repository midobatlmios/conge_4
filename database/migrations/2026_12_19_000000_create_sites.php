<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sites (head office, warehouse in another city…): each employee may belong to
 * one, and a public holiday may be local to one site (null: every site).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('city', 100)->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('holidays', fn (Blueprint $table) => $table->dropConstrainedForeignId('site_id'));
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('site_id'));
        Schema::dropIfExists('sites');
    }
};
