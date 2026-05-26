<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Snapshot quotidien du classement à 12h00 (début de chaque journée foot)
Schedule::command('rankings:snapshot')->dailyAt('12:00');
