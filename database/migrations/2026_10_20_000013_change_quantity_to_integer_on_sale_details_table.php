<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sales are always made in whole units; existing quantities are already whole numbers.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE sale_details ALTER COLUMN quantity TYPE integer USING quantity::integer');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sale_details ALTER COLUMN quantity TYPE numeric(12, 2)');
    }
};
