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

// Back up the database daily to Google Drive.
// --only-db skips zipping the codebase (already safe in Git) — DB dump only.
Schedule::command('backup:run --only-db')->daily()->at('02:00');
Schedule::command('backup:clean')->daily()->at('02:30');
Schedule::command('backup:monitor')->daily()->at('03:00');
