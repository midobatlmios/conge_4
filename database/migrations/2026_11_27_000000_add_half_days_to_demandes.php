<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Half days: a request can start in the afternoon and/or end at noon, so its
 * day count can be a multiple of 0.5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->boolean('start_half')->default(false)->after('date_fin'); // starts in the afternoon
            $table->boolean('end_half')->default(false)->after('start_half'); // ends at noon
            $table->decimal('nbr_jours', 5, 1)->change();
        });
    }

    /** Half days are lost: counts are rounded up to whole days. */
    public function down(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->integer('nbr_jours')->change();
            $table->dropColumn(['start_half', 'end_half']);
        });
    }
};
