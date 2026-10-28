<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('proctad:send-assignment-reminders')->dailyAt('08:00');
Schedule::command('proctad:expire-pending-assignments')->dailyAt('01:00');
Schedule::command('proctad:prune-logs')->monthly();

// Sessions IMIS can no longer sign out (it stops tracking them after 30 days).
Schedule::call(fn () => DB::table('imis_sessions')->where('created_at', '<', now()->subDays(30))->delete())
    ->daily()
    ->name('prune-imis-sessions');
