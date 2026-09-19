<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retrain the PHP-ML forecast model every week
Schedule::command('forecast:retrain')->weekly();

// Refresh weather (recent + short forecast) once a day, ahead of the forecast retrain
Schedule::command('weather:sync')->dailyAt('05:00')->timezone('Asia/Manila');
