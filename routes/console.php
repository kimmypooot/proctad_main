<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('proctad:send-assignment-reminders')->dailyAt('08:00');
Schedule::command('proctad:expire-pending-assignments')->dailyAt('01:00');
Schedule::command('proctad:prune-logs')->monthly();
