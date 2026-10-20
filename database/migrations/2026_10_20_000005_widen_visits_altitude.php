<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * decimal(10, 7) tops out below 1000, so any altitude over 999 m (Salta sits near 1200 m) overflowed.
     */
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->decimal('altitude', 12, 7)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->decimal('altitude', 10, 7)->nullable()->change();
        });
    }
};
