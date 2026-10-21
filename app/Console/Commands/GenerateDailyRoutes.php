<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\GenerateDailyRoutesAction;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('routes:generate {--date= : Date to plan (Y-m-d), today by default}')]
#[Description("Create each driver's route for the day from the customers' visit preferences")]
class GenerateDailyRoutes extends Command
{
    public function handle(GenerateDailyRoutesAction $generate): int
    {
        $option = $this->option('date');

        try {
            $date = $option === null ? CarbonImmutable::today() : CarbonImmutable::createFromFormat('!Y-m-d', $option);
        } catch (Throwable) {
            $date = false;
        }

        if ($date === false || $date === null) {
            $this->error("Invalid date '{$option}', expected Y-m-d.");

            return self::FAILURE;
        }

        $created = $generate->execute($date);

        $this->info("Created {$created} route(s) for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
