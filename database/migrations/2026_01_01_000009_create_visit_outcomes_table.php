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
        Schema::create('visit_outcomes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->boolean('triggers_reschedule')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->string('code', 30)->nullable()->unique();

            $table->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_outcomes');
    }
};
