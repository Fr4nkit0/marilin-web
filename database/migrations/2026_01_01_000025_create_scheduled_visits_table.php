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
        Schema::create('scheduled_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->date('date');
            $table->time('scheduled_time')->nullable();
            $table->text('note')->nullable();
            $table->string('status', 30)->default('SCHEDULED'); // SCHEDULED, CANCELLED
            $table->foreignId('route_stop_id')->nullable()->constrained('route_stops')->nullOnDelete();
            $table->boolean('created_stop')->default(false); // the stop was created for it, not a preference stop that carries it
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['date', 'status']);
            $table->index('customer_id');
        });

        // route_stops is created before scheduled_visits, so its scheduled visit FK is added here.
        Schema::table('route_stops', function (Blueprint $table) {
            $table->foreign('scheduled_visit_id')->references('id')->on('scheduled_visits')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('route_stops', function (Blueprint $table) {
            $table->dropForeign(['scheduled_visit_id']);
        });

        Schema::dropIfExists('scheduled_visits');
    }
};
