<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Once the day is over, rescheduled stops that nobody visited go to the review inbox.
Schedule::command('review:flag-rescheduled-stops')->dailyAt('01:00');
