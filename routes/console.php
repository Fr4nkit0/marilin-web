<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Each night, every driver's route for the new day is built from the customers' visit preferences.
Schedule::command('routes:generate')->dailyAt('00:30');

// Once the day is over, rescheduled stops that nobody visited go to the review inbox.
Schedule::command('review:flag-rescheduled-stops')->dailyAt('01:00');

// Planned stops whose day ended without a visit go to the review inbox; the app only shows today's route.
Schedule::command('review:flag-unvisited-stops')->dailyAt('01:00');

// Routes of a past day still planned or in progress are completed; their not done stops stay in the review inbox.
Schedule::command('routes:close-past')->dailyAt('01:00');
