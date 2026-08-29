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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('phone_number', 20)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('last_modified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
