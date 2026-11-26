<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Scheduled tasks. On the server, the Laravel scheduler must run every minute:
 *   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
 * (on a development machine: `php artisan schedule:work`).
 */

// Morning reminders on weekdays (public holidays are skipped by the command itself).
Schedule::command('reminders:send')
    ->weekdays()
    ->at(config('conge.reminders.send_at'))
    ->timezone(config('conge.reminders.timezone'))
    ->withoutOverlapping()
    ->onOneServer();
