<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attestations (de travail, de salaire): requested by the employee or created
 * by HR, then issued (numbered, with a frozen copy of what is printed) or
 * refused.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attestations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('purpose', 150)->nullable();
            $table->string('status', 20)->default('demandee')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 30)->nullable()->unique();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('refusal_reason', 255)->nullable();
            // What the PDF prints, frozen when issued (encrypted: CIN, salary).
            $table->text('snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attestations');
    }
};
