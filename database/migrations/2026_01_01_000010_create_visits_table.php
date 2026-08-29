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
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_stop_id')->nullable()->constrained('route_stops')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('outcome_id')->nullable()->constrained('visit_outcomes')->nullOnDelete();
            $table->dateTime('arrived_at')->useCurrent();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('altitude', 10, 7)->nullable();
            $table->string('status', 30)->default('PENDING'); // PENDING, IN_PROGRESS, FINISHED, CANCELLED
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('last_modified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['customer_id', 'arrived_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
