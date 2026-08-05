<?php

namespace App\Services;

use App\Models\AnomalyAlert;
use App\Models\BuildingDaily;
use App\Models\CullRecord;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\HenBatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AiInsightService
{
    private const FALLBACK_MESSAGE = 'AI insights temporarily unavailable.';

    // Mirrors RecommendationService's CULL_TARGET_AGE_WEEKS (140) minus
    // CULL_WINDOW_LEAD_WEEKS (4) — duplicated rather than imported since
    // RecommendationService is out of scope to touch for this phase.
    private const CULL_WINDOW_START_AGE_WEEKS = 136;
    private const CULL_READINESS_AGE_WEEKS = 132; // mirrors RecommendationService::CULL_READINESS_AGE_WEEKS

    /**
     * Generate (or read from cache) today's AI insight. Cached per calendar
     * day, not per rolling hour — a dashboard reload should never re-call the
     * API for a day that's already been narrated, only $forceRegenerate
     * (the manual refresh button) should bypass the cache.
     */
    public function getInsight(bool $forceRegenerate = false): string
    {
        $cacheKey = $this->cacheKeyForToday();

        if (! $forceRegenerate) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $apiKey = config('services.ai.api_key');
        $baseUrl = rtrim(config('services.ai.base_url'), '/');
        $model = config('services.ai.model');

        if (! $apiKey) {
            return self::FALLBACK_MESSAGE;
        }

        [$systemMessage, $userMessage] = $this->buildPrompt();

        try {
            $response = Http::withToken($apiKey)
                ->timeout(20)
                ->post("{$baseUrl}/chat/completions", [
                    'model'       => $model,
                    'temperature' => 0.3,
                    // Reasoning models (e.g. Groq's openai/gpt-oss-120b) spend part of this
                    // budget on a hidden reasoning pass before producing visible content —
                    // 220 was tuned for a plain chat model and left content empty here.
                    'max_tokens'  => 600,
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemMessage],
                        ['role' => 'user',   'content' => $userMessage],
                    ],
                ]);

            if (! $response->successful()) {
                return self::FALLBACK_MESSAGE;
            }

            // Standard OpenAI-compatible chat completions shape — Groq's endpoint
            // returns the same choices[0].message.content field. But a reasoning
            // model can return content as "" (not null) if it runs out of budget
            // mid-reasoning, and ?? only catches null — so check emptiness
            // explicitly and never cache a blank result as if it were real.
            $insight = trim((string) $response->json('choices.0.message.content'));

            if ($insight === '') {
                return self::FALLBACK_MESSAGE;
            }

            Cache::put($cacheKey, $insight, Carbon::today()->endOfDay());

            return $insight;
        } catch (\Throwable) {
            return self::FALLBACK_MESSAGE;
        }
    }

    public function currentModel(): string
    {
        return config('services.ai.model');
    }

    public function currentProvider(): string
    {
        return config('services.ai.provider');
    }

    private function cacheKeyForToday(): string
    {
        return 'ai_insight_' . Carbon::today()->format('Y-m-d');
    }

    /** @return array{0: string, 1: string} [systemMessage, userMessage] */
    private function buildPrompt(): array
    {
        $today      = Carbon::today();
        $eggsToday  = EggProduction::whereDate('date', $today)->sum('eggs_collected');
        $activeHens = HenBatch::activeHenCount();
        $revToday   = EggSale::whereDate('date', $today)->sum('total_amount');
        $prodRate   = $activeHens > 0 ? round(($eggsToday / $activeHens) * 100, 1) : 0;
        $soldToday  = EggSale::whereDate('date', $today)->sum('quantity');
        $remaining  = max(0, $eggsToday - $soldToday);

        $avg7day = round(
            EggProduction::where('date', '>=', Carbon::today()->subDays(7))
                ->where('date', '<', $today)
                ->avg('eggs_collected') ?? 0
        );

        $salesLast7 = round(
            EggSale::where('date', '>=', Carbon::today()->subDays(7))
                ->where('date', '<', $today)
                ->sum('total_amount')
        );

        $forecast       = (new ForecastService)->forecast();
        $mape           = $forecast['active'] ? $forecast['mape'] . '%' : 'N/A';
        $forecast7Total = $forecast['active'] ? collect($forecast['forecast_7day'])->sum('predicted') : 'N/A';
        $forecast30Rev  = $forecast['active']
            ? '₱' . number_format(collect($forecast['forecast_30day'])->sum('predicted_revenue'), 2)
            : 'N/A';

        $activeAlerts = AnomalyAlert::where('alert_date', '>=', Carbon::today()->subDays(7))
            ->where('status', 'unreviewed')
            ->get(['type', 'severity', 'description'])
            ->map(fn ($a) => "[{$a->severity}] {$a->type}: {$a->description}")
            ->implode(' | ');

        // ── Mortality ────────────────────────────────────────────────────────
        $mortalityToday = (int) (EggProduction::whereDate('date', $today)->value('mortality') ?? 0);
        $mortalityAvg7  = round(
            EggProduction::where('date', '>=', Carbon::today()->subDays(7))
                ->where('date', '<', $today)
                ->avg('mortality') ?? 0,
            1
        );

        // ── Cull events, last 7 days ────────────────────────────────────────
        $cullSummary = $this->cullEventsSummary();

        // ── Buildings approaching cull-readiness (132+ weeks) ────────────────
        $cullReadinessSummary = $this->cullReadinessSummary();

        $systemMessage = <<<'SYSMSG'
You are a professional agricultural and poultry data analyst for SPC Farm Magalang, a commercial egg-laying farm in Sta. Maria, Magalang, Pampanga, Philippines.
Analyze the farm data provided and respond with EXACTLY 3 concise, actionable bullet points (each starting with "•").
Each bullet must cite specific numbers from the data. Do not add a preamble or closing sentence — bullets only.
Focus on: current production health, forecast outlook, mortality/culling context, and the most critical action the farm manager should take today.
SYSMSG;

        $userMessage = "Today's farm data for SPC Farm Magalang:\n"
            . "• Eggs collected today: {$eggsToday}\n"
            . "• Active hens: {$activeHens}\n"
            . "• Production rate today: {$prodRate}%\n"
            . "• Revenue today: ₱" . number_format($revToday, 2) . "\n"
            . "• Unsold eggs today: {$remaining}\n"
            . "• 7-day average daily production: {$avg7day} eggs\n"
            . "• Sales revenue last 7 days: ₱" . number_format($salesLast7, 2) . "\n"
            . "• PHP-ML 7-day production forecast total: {$forecast7Total} eggs\n"
            . "• PHP-ML 30-day revenue forecast: {$forecast30Rev}\n"
            . "• Model MAPE accuracy: {$mape}\n"
            . "• Mortality today: {$mortalityToday}\n"
            . "• 7-day average mortality: {$mortalityAvg7}\n"
            . "• Cull events (last 7 days): {$cullSummary}\n"
            . "• Buildings approaching cull-readiness (132+ weeks): {$cullReadinessSummary}\n"
            . ($activeAlerts ? "• Active anomaly alerts: {$activeAlerts}" : '• No active anomaly alerts.');

        return [$systemMessage, $userMessage];
    }

    private function cullEventsSummary(): string
    {
        $culls = CullRecord::where('date', '>=', Carbon::today()->subDays(7))
            ->with('henBatch')
            ->get();

        if ($culls->isEmpty()) {
            return 'None in the last 7 days.';
        }

        return $culls->map(function (CullRecord $cull) {
            $building = $cull->henBatch?->building_no ?? $cull->henBatch?->building ?? 'unknown';
            $planned  = $cull->henBatch ? $this->wasInsideCullWindow($cull->henBatch, $cull->date) : null;
            $label    = $planned === null ? 'unknown window' : ($planned ? 'planned, in cull window' : 'unplanned');

            return "Bldg {$building} — {$cull->quantity_culled} heads ({$label})";
        })->implode(' | ');
    }

    private function cullReadinessSummary(): string
    {
        $batches = HenBatch::where('status', 'Active')->whereNotNull('building_no')->get()->keyBy('id');

        $latestPerBatch = BuildingDaily::whereIn('hen_batch_id', $batches->keys())
            ->orderBy('date')
            ->get()
            ->groupBy('hen_batch_id')
            ->map(fn ($rows) => $rows->last());

        $approaching = [];
        foreach ($latestPerBatch as $henBatchId => $row) {
            if ($row->age_weeks === null) {
                continue;
            }

            $daysSince   = $row->date->diffInDays(Carbon::today());
            $currentAge  = $row->age_weeks + (int) floor($daysSince / 7);

            if ($currentAge >= self::CULL_READINESS_AGE_WEEKS) {
                $building = $batches->get($henBatchId)?->building_no ?? '?';
                $approaching[] = "Bldg {$building} ({$currentAge}wk)";
            }
        }

        return empty($approaching) ? 'None.' : implode(', ', $approaching);
    }

    private function wasInsideCullWindow(HenBatch $batch, Carbon $cullDate): ?bool
    {
        $age = $this->ageAtDate($batch, $cullDate);

        return $age === null ? null : $age >= self::CULL_WINDOW_START_AGE_WEEKS;
    }

    private function ageAtDate(HenBatch $batch, Carbon $date): ?int
    {
        if ($batch->placement_date) {
            return (int) floor($batch->placement_date->diffInDays($date) / 7);
        }

        $row = BuildingDaily::where('hen_batch_id', $batch->id)
            ->orderByRaw('ABS(DATEDIFF(date, ?)) asc', [$date->format('Y-m-d')])
            ->first();

        if (! $row || $row->age_weeks === null) {
            return null;
        }

        $dayDiff = $row->date->diffInDays($date, false);

        return (int) round($row->age_weeks + $dayDiff / 7);
    }
}
