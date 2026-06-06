<?php

namespace App\Console\Commands;

use App\Services\ForecastService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetrainForecastModel extends Command
{
    protected $signature   = 'forecast:retrain';
    protected $description = 'Retrain the PHP-ML production forecast model and log results';

    public function handle(ForecastService $service): int
    {
        $result = $service->forecast(persist: true);

        if (! $result['active']) {
            $this->warn($result['message']);
            return self::SUCCESS;
        }

        $this->info("Model retrained on {$result['trained_on']} records.");
        $this->info("MAPE: {$result['mape']}%");
        $this->info("7-day production forecast and 30-day revenue forecast generated.");

        Log::info('forecast:retrain completed', [
            'trained_on'   => $result['trained_on'],
            'mape'         => $result['mape'],
            'last_trained' => $result['last_trained'],
        ]);

        return self::SUCCESS;
    }
}
