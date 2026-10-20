<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drivers write the floor as free text ("PB", "3B", "Local 2"); existing integers become their text form.
     */
    public function up(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('floor', 20)->nullable()->change();
        });
    }

    /**
     * Floors that are not whole numbers cannot go back to an integer column and are cleared.
     */
    public function down(): void
    {
        DB::table('customer_addresses')->whereNotNull('floor')->where('floor', '!~', '^-?[0-9]{1,4}$')->update(['floor' => null]);

        DB::statement('ALTER TABLE customer_addresses ALTER COLUMN floor TYPE smallint USING floor::smallint');
    }
};
