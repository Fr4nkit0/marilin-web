<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that take part in the pull and are filtered by updated_at.
     *
     * @var array<int, string>
     */
    private array $tables = [
        'routes',
        'route_stops',
        'route_inventories',
        'customers',
        'customer_addresses',
        'customer_preferences',
        'customer_agreements',
        'customer_balances',
        'customer_container_balances',
        'products',
        'container_types',
        'payment_methods',
        'visit_outcomes',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->index('updated_at');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropIndex(['updated_at']);
            });
        }
    }
};
