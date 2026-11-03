<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('staff:password-reminders')
    ->dailyAt('08:00')->timezone('Africa/Nairobi')->withoutOverlapping();

Schedule::command('hr:check-expiring-contracts')
    ->dailyAt('08:00')->timezone('Africa/Nairobi')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
