<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal data protection (loi 09-08 / CNDP):
 * - a log of who viewed or changed someone else's personal data;
 * - the day each employee acknowledged the information notice;
 * - the day a former employee was anonymized (retention);
 * - the CNDP declaration number and privacy contact shown in the notice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_data_accesses', function (Blueprint $table) {
            $table->id();
            // Who did it, and whose data: kept when either account is anonymized.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40)->index();
            $table->string('detail', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('privacy_acknowledged_at')->nullable()->after('deactivated_at');
            $table->timestamp('anonymized_at')->nullable()->after('privacy_acknowledged_at');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('cndp_number', 50)->nullable()->after('brand_color');
            $table->string('privacy_contact', 150)->nullable()->after('cndp_number');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['cndp_number', 'privacy_contact']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['privacy_acknowledged_at', 'anonymized_at']);
        });

        Schema::dropIfExists('personal_data_accesses');
    }
};
