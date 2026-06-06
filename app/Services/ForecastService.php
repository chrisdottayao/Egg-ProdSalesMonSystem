<?php

namespace App\Services;

use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\ForecastEvaluation;
use Illuminate\Support\Carbon;
use Phpml\Regression\LeastSquares;

class ForecastService
{
    public function forecast(bool $persist = false): array
    {
        $records = EggProduction::orderBy('date')->get(['date', 'eggs_collected']);
        $n       = $records->count();

        if ($n < 30) {
            return [
                'active'  => false,
                'message' => 'Forecasting activates after 30 days of recorded data',
            ];
        }

        // X = day indices [[1],[2],...,[n]], Y = eggs collected
        $X = array_map(fn($i) => [$i + 1], range(0, $n - 1));
        $Y = $records->pluck('eggs_collected')->map(fn($v) => (float) $v)->toArray();

        $model = new LeastSquares();
        $model->train($X, $Y);

        $avgPrice = (float) (EggSale::avg('price_per_unit') ?? 9.0);
        $lastDate = $records->last()->date;

        // 7-day production forecast
        $forecast7day = [];
        for ($d = 1; $d <= 7; $d++) {
            $predicted    = max(0, (int) round($model->predict([$n + $d])));
            $forecast7day[] = [
                'day'       => Carbon::parse($lastDate)->addDays($d)->format('M d'),
                'predicted' => $predicted,
            ];
        }

        // 30-day revenue forecast
        $forecast30day = [];
        for ($d = 1; $d <= 30; $d++) {
            $predicted      = max(0, (int) round($model->predict([$n + $d])));
            $forecast30day[] = [
                'day'               => Carbon::parse($lastDate)->addDays($d)->format('M d'),
                'predicted_revenue' => round($predicted * $avgPrice, 2),
            ];
        }

        // MAPE — retrain on first n-7 records, predict last 7 as holdout
        $holdout   = 7;
        $trainX    = array_slice($X, 0, $n - $holdout);
        $trainY    = array_slice($Y, 0, $n - $holdout);
        $mapeModel = new LeastSquares();
        $mapeModel->train($trainX, $trainY);

        $mapeValues = [];
        for ($i = 0; $i < $holdout; $i++) {
            $idx       = $n - $holdout + $i;
            $actual    = $Y[$idx];
            $predicted = $mapeModel->predict([$idx + 1]);
            if ($actual > 0) {
                $mapeValues[] = abs($actual - $predicted) / $actual * 100;
            }
        }
        $mape = count($mapeValues) > 0
            ? round(array_sum($mapeValues) / count($mapeValues), 2)
            : 0.0;

        $result = [
            'active'         => true,
            'forecast_7day'  => $forecast7day,
            'forecast_30day' => $forecast30day,
            'mape'           => $mape,
            'trained_on'     => $n,
            'last_trained'   => now()->format('Y-m-d H:i:s'),
        ];

        if ($persist) {
            ForecastEvaluation::create([
                'trained_on'           => $n,
                'mape'                 => $mape,
                'forecast_7day_total'  => collect($forecast7day)->sum('predicted'),
                'forecast_30day_total' => collect($forecast30day)->sum('predicted_revenue'),
                'evaluated_at'         => now(),
            ]);
        }

        return $result;
    }
}
