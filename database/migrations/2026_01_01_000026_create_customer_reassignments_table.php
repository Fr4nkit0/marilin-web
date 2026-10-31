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
        // Every change of a customer's driver, so the pull can tell the driver it left (customers_removed).
        Schema::create('customer_reassignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('from_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('to_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('reassigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reassigned_at');

            $table->index(['from_driver_id', 'reassigned_at']);
            $table->index(['to_driver_id', 'reassigned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_reassignments');
    }
};
