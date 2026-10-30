<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Each job runs inside the scheduler's own process (Artisan::call) instead of spawning `php artisan`,
// because many shared hosts disable proc_open and `schedule:run` then fails with a Process error.
$job = fn (string $command) => Schedule::call(fn () => Artisan::call($command))->name($command);

/*
 * Run with `php artisan schedule:work` (development / XAMPP window) or a
 * Windows Task Scheduler entry for `php artisan schedule:run` every minute.
 * See docs/instalasi-xampp.md.
 */
$job('reminders:dispatch')->everyMinute()->withoutOverlapping(5);

// Push deliveries are queued. Draining the queue from the scheduler means
// XAMPP setups only need one background process.
$job('queue:work --stop-when-empty --max-time=50 --tries=1')->everyMinute()->withoutOverlapping(2);

// Daily database backup at 02:00 local time; keeps the newest BACKUP_KEEP files.
$job('usandus:backup')->dailyAt('02:00')->timezone(config('usandus.default_timezone'));

// Scheduled love notes are posted into the chat at the time the sender picked.
$job('chat:deliver-notes')->everyMinute()->withoutOverlapping(5);

// Everyone is told once when a new web build is put on the server (the PWA and the app then offer a reload).
$job('usandus:kabari-build')->everyFiveMinutes()->withoutOverlapping(5);

// Morning summary for people who turned the digest on (08:00 their time, or the first run after).
$job('digest:send')->hourly()->withoutOverlapping(10);

// Original photobooth shots are kept a day after saving (to edit again); the saved pictures stay in the album.
$job('photobooth:prune')->hourly()->withoutOverlapping(10);

// Today's quote for every couple, written by the AI when a key is set (also written on demand when the dashboard opens).
$job('usandus:kutipan')->hourly()->withoutOverlapping(10);
