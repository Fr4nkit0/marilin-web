<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A driver's change to another driver's customer data, held until the admin approves or rejects it (ADR 0001).
        Schema::create('pending_customer_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('target', 30); // customer, customer_address, customer_preference
            $table->unsignedBigInteger('target_id')->nullable(); // the row it edits; null when it adds an address or preference
            $table->uuid('client_uuid'); // the app's id of the record it came in
            $table->jsonb('changes'); // the fields it proposes, as the app sent them
            $table->timestamp('modified_at'); // when the driver made it, in the app
            $table->foreignId('proposed_by')->constrained('users');
            $table->string('status', 20)->default('PENDING'); // PENDING, APPROVED, REJECTED
            $table->boolean('needs_review')->default(true);
            $table->jsonb('review_reasons')->default('[]');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_uuid', 'target']);
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_customer_changes');
    }
};
