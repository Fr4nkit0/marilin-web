<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entities the driver app can create; customers already carry their own client_uuid.
     *
     * @var array<int, string>
     */
    private array $tables = ['route_stops', 'visits', 'container_movements', 'sales', 'payments'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->uuid('client_uuid')->nullable()->unique();
            });
        }

        Schema::dropIfExists('idempotency_records');
    }

    public function down(): void
    {
        Schema::create('idempotency_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('entity_type', 50);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['entity_type', 'user_id']);
        });

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('client_uuid');
            });
        }
    }
};
