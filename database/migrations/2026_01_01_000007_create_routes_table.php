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
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assigned_repartidor_id')->constrained('repartidores');
            $table->date('date');
            $table->string('status', 30)->default('PLANNED'); // PLANNED, IN_PROGRESS, COMPLETED, CANCELLED
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['date', 'assigned_repartidor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
