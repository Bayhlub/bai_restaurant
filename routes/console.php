<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Hourly rather than nightly, because the restaurant PC is switched off
 * overnight and a 3am schedule would simply never fire. The command itself
 * skips the run once the day already has a snapshot, so this costs nothing.
 */
Schedule::command('backup:run')->hourly()->withoutOverlapping();
