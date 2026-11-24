<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail of leave requests: who created, edited, accepted/refused or
 * deleted each one, and when. Append-only.
 *
 * demande_id has no foreign key on purpose: the trail must outlive a deleted
 * request (disputes, labour inspections). employee_id keeps who it was for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demande_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('demande_id')->index();
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            // Who did it; null when done outside a signed-in request (console, migration).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20); // created, updated, accepted, refused, reopened, deleted
            // {"field": {"old": …, "new": …}} for updates; a snapshot for created/deleted.
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demande_events');
    }
};
