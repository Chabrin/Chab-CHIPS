<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('staff:password-reminders')
    ->dailyAt('08:00')->timezone('Africa/Nairobi')->withoutOverlapping();

Schedule::command('hr:check-expiring-contracts')
    ->dailyAt('08:00')->timezone('Africa/Nairobi')->withoutOverlapping();

// Work permits and Alien IDs that expire soon or already have (HR Manager and Directors, in the bell)
Schedule::command('hr:check-expiring-permits')
    ->dailyAt('08:00')->timezone('Africa/Nairobi')->withoutOverlapping();

// Also catches transfers approved after the start of their effective date.
Schedule::command('property-transfers:remind-due')
    ->everyFiveMinutes()->timezone('Africa/Nairobi')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
