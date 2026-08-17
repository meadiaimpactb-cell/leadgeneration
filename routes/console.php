<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
| Needs one cron entry on the server running `schedule:run` every minute —
| docs/deployment.md. Without it nothing below ever fires, and the failure is
| silent, which is why it is written down there rather than assumed.
*/

/*
 * The daily digest (§6.2, the notifications screen's approved scope).
 *
 * Early morning local time so it is waiting when the team starts, and
 * `withoutOverlapping` because a mail server that has gone slow must not
 * leave two summaries stacked up behind each other.
 */
Schedule::command('amad:daily-summary')
    ->dailyAt('07:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();
