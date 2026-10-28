<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->boolean('requires_container')->default(true);
            $table->foreignId('container_type_id')->nullable()->constrained('container_types')->nullOnDelete();
            $table->decimal('available_quantity', 14, 2)->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->decimal('price', 12, 2)->default(0);

            $table->index('updated_at');
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_non_negative CHECK (price >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
