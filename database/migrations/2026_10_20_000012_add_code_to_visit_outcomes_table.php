<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stable identifier the mobile app uses to pick how an outcome is shown (absent, refused, reschedule).
     */
    public function up(): void
    {
        Schema::table('visit_outcomes', function (Blueprint $table) {
            $table->string('code', 30)->nullable()->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visit_outcomes', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
