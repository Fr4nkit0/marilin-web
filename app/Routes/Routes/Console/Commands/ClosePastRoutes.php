<?php

declare(strict_types=1);

namespace App\Routes\Routes\Console\Commands;

use App\Routes\Routes\RouteProgress;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('routes:close-past')]
#[Description('Complete the routes of past days still planned or in progress')]
class ClosePastRoutes extends Command
{
    public function handle(RouteProgress $progress): int
    {
        $closed = $progress->closePastDays();

        $this->info("Closed {$closed} past route(s).");

        return self::SUCCESS;
    }
}
