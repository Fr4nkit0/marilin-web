<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Once the day is over, rescheduled stops that nobody visited go to the review inbox.
Schedule::command('review:flag-rescheduled-stops')->dailyAt('01:00');

// Planned stops whose day ended without a visit go to the review inbox; the app only shows today's route.
Schedule::command('review:flag-unvisited-stops')->dailyAt('01:00');
