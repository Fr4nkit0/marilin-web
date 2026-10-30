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
        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('address_id')->constrained('customer_addresses')->cascadeOnDelete();
            $table->foreignId('is_rescheduled_from')->nullable()->constrained('route_stops')->nullOnDelete();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->time('scheduled_time')->nullable();
            $table->foreignId('scheduled_visit_id')->nullable(); // the scheduled visit it carries; its FK lives in the scheduled_visits migration
            $table->text('note')->nullable();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->boolean('needs_review')->default(false);
            $table->text('review_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index('updated_at');
            $table->index('scheduled_visit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_stops');
    }
};
