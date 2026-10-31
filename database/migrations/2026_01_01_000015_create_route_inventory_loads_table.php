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
        Schema::create('route_inventory_loads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 14, 2);
            // A correction fixes a load written down wrong (from Revisión) and keeps the admin's reason.
            $table->string('type', 20)->default('load');
            $table->text('reason')->nullable();
            $table->dateTime('loaded_at')->useCurrent();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('route_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_inventory_loads');
    }
};
