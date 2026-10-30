<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;

if (! function_exists('push')) {
    /**
     * @param  array<string, array<int, array<string, mixed>>>  $records
     */
    function push(array $records): TestResponse
    {
        return test()->postJson('/api/v1/sync/push', $records);
    }
}
