<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BuildingDaily;
use App\Models\EggGradingDaily;
use App\Models\HenBatch;
use App\Services\ProductionRollupService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BulkImportController extends Controller
{
    private const IMPORT_CHUNK_SIZE = 500;

    // ── Templates ─────────────────────────────────────────────────────────

    public function buildingDailyTemplate()
    {
        $headers = [
            'date', 'building_no', 'population', 'mortality', 'net_birds',
            'feed_bags', 'eggs_house', 'eggs_eggroom', 'soft_shell', 'age_weeks', 'prod_rate',
        ];

        $rows = [
            ['2026-07-01', 1, 1874, 1, 1873, 4.0, 1547, 1547, 12, 94, 82.5507],
            ['2026-07-01', 2, 1940, 1, 1939, 4.5, 1490, 1490, 7, 103, 76.8041],
            ['2026-07-01', 3, 1894, 0, 1894, 4.0, 1588, 1588, 9, 103, 83.8437],
        ];

        return response()->stream(function () use ($headers, $rows) {
            $h = fopen('php://output', 'w');
            fputcsv($h, $headers);
            foreach ($rows as $row) {
                fputcsv($h, $row);
            }
            fclose($h);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="building_daily_template.csv"',
        ]);
    }

    public function eggGradingTemplate()
    {
        $headers = ['date', 'category', 'cases', 'trays', 'pieces', 'total_pcs'];

        $rows = [
            ['2026-07-01', 'Large', 20, 5, 3, 7353],
            ['2026-07-01', 'Medium', 35, 0, 16, 12616],
            ['2026-07-01', 'Small', 58, 1, 7, 20917],
        ];

        return response()->stream(function () use ($headers, $rows) {
            $h = fopen('php://output', 'w');
            fputcsv($h, $headers);
            foreach ($rows as $row) {
                fputcsv($h, $row);
            }
            fclose($h);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="egg_grading_daily_template.csv"',
        ]);
    }

    // ── building_daily import ────────────────────────────────────────────────

    public function importBuildingDaily(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:10240']);

        $handle    = fopen($request->file('csv_file')->getPathname(), 'r');
        $headers   = null;
        $rowNumber = 0;
        $stats     = ['imported' => 0, 'skipped' => 0, 'errors' => 0, 'hen_batches_created' => 0, 'prod_rate_mismatches' => 0];
        $errors    = [];

        // ── Pass 1: read every row into memory, grouped by building_no ─────────
        // Needed because placement_date depends on each building's EARLIEST row
        // across the whole file, which we can't know mid-stream in a single pass.
        $rowsByBuilding = [];

        while (($line = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers    = array_map(fn ($h) => strtolower(trim($h)), $line);
                $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
                continue;
            }

            $rowNumber++;

            if (count(array_filter($line, fn ($v) => trim($v) !== '')) === 0) {
                continue;
            }

            $line = array_pad($line, count($headers), '');
            $data = array_combine($headers, array_map('trim', array_slice($line, 0, count($headers))));

            try {
                $date = Carbon::parse($data['date'] ?? '');
                if (empty(trim($data['date'] ?? ''))) throw new \Exception('Empty');
            } catch (\Throwable) {
                $errors[] = "Row {$rowNumber}: Invalid or missing date — '{$data['date']}'.";
                $stats['errors']++;
                continue;
            }

            $buildingNo = (int) ($data['building_no'] ?? 0);
            if ($buildingNo <= 0) {
                $errors[] = "Row {$rowNumber} ({$date->format('Y-m-d')}): building_no must be a positive integer.";
                $stats['errors']++;
                continue;
            }

            $ageWeeks = (int) ($data['age_weeks'] ?? -1);
            if ($ageWeeks < 0) {
                $errors[] = "Row {$rowNumber} ({$date->format('Y-m-d')}, building {$buildingNo}): age_weeks is required.";
                $stats['errors']++;
                continue;
            }

            $population = (int) ($data['population'] ?? 0);
            $eggsHouse  = (int) ($data['eggs_house'] ?? 0);

            $rowsByBuilding[$buildingNo][] = [
                'row_number'    => $rowNumber,
                'date'          => $date->format('Y-m-d'),
                'population'    => $population,
                'mortality'     => max(0, (int) ($data['mortality'] ?? 0)),
                'net_birds'     => (int) ($data['net_birds'] ?? 0),
                // Blank feed_bags is a continuation-line artifact — null, not 0
                // (zero feed is a real signal to the Phase 4 rules; blank is not).
                'feed_bags'     => is_numeric($data['feed_bags'] ?? '') && $data['feed_bags'] !== ''
                                        ? (float) $data['feed_bags'] : null,
                'eggs_house'    => $eggsHouse,
                'eggs_eggroom'  => (int) ($data['eggs_eggroom'] ?? 0),
                'soft_shell'    => max(0, (int) ($data['soft_shell'] ?? 0)),
                'age_weeks'     => $ageWeeks,
                'csv_prod_rate' => is_numeric($data['prod_rate'] ?? '') ? (float) $data['prod_rate'] : null,
            ];
        }
        fclose($handle);

        DB::beginTransaction();

        try {
            // ── Pass 2: resolve building_no -> HenBatch (create if new) ─────────
            $existingBatches = HenBatch::whereIn('building_no', array_keys($rowsByBuilding))
                ->get()
                ->keyBy('building_no');

            $batchIdByBuildingNo = [];

            foreach ($rowsByBuilding as $buildingNo => $rows) {
                usort($rows, fn ($a, $b) => strcmp($a['date'], $b['date']));
                $rowsByBuilding[$buildingNo] = $rows;

                $existing = $existingBatches->get($buildingNo);
                if ($existing) {
                    $batchIdByBuildingNo[$buildingNo] = $existing->id;
                    continue;
                }

                $earliest = $rows[0];
                // placement_date = earliest row's date - (its age_weeks * 7 days),
                // derived ONCE from the earliest row only — recomputing per row
                // would jitter by a few days since age_weeks is an integer and
                // consecutive days round to the same week.
                $placementDate = Carbon::parse($earliest['date'])->subDays($earliest['age_weeks'] * 7);

                $batch = HenBatch::create([
                    'batch_id'       => sprintf('HB-BLDG-%02d', $buildingNo),
                    'batch_size'     => $earliest['population'],
                    'status'         => 'Active',
                    'entry_date'     => $placementDate->format('Y-m-d'),
                    'placement_date' => $placementDate->format('Y-m-d'),
                    'breed'          => 'Dekalb White', // confirmed by the farm
                    'building'       => (string) $buildingNo,
                    'building_no'    => $buildingNo,
                ]);

                $batchIdByBuildingNo[$buildingNo] = $batch->id;
                $stats['hen_batches_created']++;
            }

            // ── Preload existing (date, hen_batch_id) keys — no per-row exists() ──
            $existingKeys = BuildingDaily::whereIn('hen_batch_id', $batchIdByBuildingNo)
                ->get(['date', 'hen_batch_id'])
                ->map(fn ($r) => $r->date->format('Y-m-d') . '|' . $r->hen_batch_id)
                ->flip()
                ->all();

            $insertRows   = [];
            $distinctDates = [];

            foreach ($rowsByBuilding as $buildingNo => $rows) {
                $henBatchId = $batchIdByBuildingNo[$buildingNo];

                foreach ($rows as $row) {
                    $key = $row['date'] . '|' . $henBatchId;
                    if (isset($existingKeys[$key])) {
                        $stats['skipped']++;
                        continue;
                    }

                    $prodRate = $row['population'] > 0
                        ? round(($row['eggs_house'] / $row['population']) * 100, 2)
                        : 0;

                    if ($row['csv_prod_rate'] !== null && abs(round($row['csv_prod_rate'], 2) - $prodRate) > 0.01) {
                        $stats['prod_rate_mismatches']++;
                        $errors[] = sprintf(
                            "Row %d (%s, building %d): prod_rate mismatch — file says %.4f, computed %.2f from eggs_house/population.",
                            $row['row_number'], $row['date'], $buildingNo, $row['csv_prod_rate'], $prodRate
                        );
                    }

                    $insertRows[] = [
                        'date'         => $row['date'],
                        'hen_batch_id' => $henBatchId,
                        'population'   => $row['population'],
                        'mortality'    => $row['mortality'],
                        'net_birds'    => $row['net_birds'],
                        'feed_bags'    => $row['feed_bags'],
                        'eggs_house'   => $row['eggs_house'],
                        'eggs_eggroom' => $row['eggs_eggroom'],
                        'soft_shell'   => $row['soft_shell'],
                        'age_weeks'    => $row['age_weeks'],
                        'prod_rate'    => $prodRate,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ];
                    $existingKeys[$key] = true;
                    $distinctDates[$row['date']] = true;
                    $stats['imported']++;

                    if (count($insertRows) >= self::IMPORT_CHUNK_SIZE) {
                        BuildingDaily::insert($insertRows);
                        $insertRows = [];
                    }
                }
            }

            if (! empty($insertRows)) {
                BuildingDaily::insert($insertRows);
            }

            // Bulk insert bypasses BuildingDaily's saved/deleted model events, so
            // the rollup never fires on its own — call it once per DISTINCT date
            // (27 calls for 1,173 rows), not once per row.
            $rollup = new ProductionRollupService;
            foreach (array_keys($distinctDates) as $date) {
                $rollup->rollupDate($date);
            }

            AuditLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'import',
                'model_type' => 'BuildingDailyCsvImport',
                'model_id'   => null,
                'details'    => $stats,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $errorToken = null;
        if (! empty($errors)) {
            $errorToken = Str::uuid()->toString();
            Cache::put("bulk_import_errors_{$errorToken}", $errors, now()->addHour());
        }

        return redirect()->route('imports.index')->with([
            'building_import_summary' => $stats,
            'import_error_token'      => $errorToken,
        ]);
    }

    // ── egg_grading_daily import ─────────────────────────────────────────────

    public function importEggGrading(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:10240']);

        $handle    = fopen($request->file('csv_file')->getPathname(), 'r');
        $headers   = null;
        $rowNumber = 0;
        $stats     = ['imported' => 0, 'skipped' => 0, 'errors' => 0, 'total_pcs_mismatches' => 0];
        $errors    = [];

        $existingKeys = EggGradingDaily::get(['date', 'category'])
            ->map(fn ($r) => $r->date->format('Y-m-d') . '|' . $r->category)
            ->flip()
            ->all();

        $insertRows = [];

        DB::beginTransaction();

        try {
            while (($line = fgetcsv($handle)) !== false) {
                if ($headers === null) {
                    $headers    = array_map(fn ($h) => strtolower(trim($h)), $line);
                    $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
                    continue;
                }

                $rowNumber++;

                if (count(array_filter($line, fn ($v) => trim($v) !== '')) === 0) {
                    continue;
                }

                $line = array_pad($line, count($headers), '');
                $data = array_combine($headers, array_map('trim', array_slice($line, 0, count($headers))));

                try {
                    $date = Carbon::parse($data['date'] ?? '');
                    if (empty(trim($data['date'] ?? ''))) throw new \Exception('Empty');
                } catch (\Throwable) {
                    $errors[] = "Row {$rowNumber}: Invalid or missing date — '{$data['date']}'.";
                    $stats['errors']++;
                    continue;
                }

                $category = $data['category'] ?? '';
                if (! in_array($category, EggGradingDaily::CATEGORIES, true)) {
                    $errors[] = "Row {$rowNumber} ({$date->format('Y-m-d')}): '{$category}' is not one of the 12 recognized categories.";
                    $stats['errors']++;
                    continue;
                }

                $dateKey = $date->format('Y-m-d');
                $key     = $dateKey . '|' . $category;
                if (isset($existingKeys[$key])) {
                    $stats['skipped']++;
                    continue;
                }

                $cases  = (int) ($data['cases'] ?? 0);
                $trays  = (int) ($data['trays'] ?? 0);
                $pieces = (int) ($data['pieces'] ?? 0);
                $totalPcs = ($cases * 360) + ($trays * 30) + $pieces;

                $csvTotalPcs = is_numeric($data['total_pcs'] ?? '') ? (int) $data['total_pcs'] : null;
                if ($csvTotalPcs !== null && $csvTotalPcs !== $totalPcs) {
                    $stats['total_pcs_mismatches']++;
                    $errors[] = "Row {$rowNumber} ({$dateKey}, {$category}): total_pcs mismatch — file says {$csvTotalPcs}, computed {$totalPcs} from cases/trays/pieces.";
                }

                $insertRows[] = [
                    'date'       => $dateKey,
                    'category'   => $category,
                    'cases'      => $cases,
                    'trays'      => $trays,
                    'pieces'     => $pieces,
                    'total_pcs'  => $totalPcs,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $existingKeys[$key] = true;
                $stats['imported']++;

                if (count($insertRows) >= self::IMPORT_CHUNK_SIZE) {
                    EggGradingDaily::insert($insertRows);
                    $insertRows = [];
                }
            }
            fclose($handle);

            if (! empty($insertRows)) {
                EggGradingDaily::insert($insertRows);
            }

            AuditLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'import',
                'model_type' => 'EggGradingDailyCsvImport',
                'model_id'   => null,
                'details'    => $stats,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw $e;
        }

        $errorToken = null;
        if (! empty($errors)) {
            $errorToken = Str::uuid()->toString();
            Cache::put("bulk_import_errors_{$errorToken}", $errors, now()->addHour());
        }

        return redirect()->route('imports.index')->with([
            'grading_import_summary' => $stats,
            'import_error_token'     => $errorToken,
        ]);
    }

    public function downloadImportErrors(string $token)
    {
        $errors = Cache::get("bulk_import_errors_{$token}");

        if (! $errors) {
            return redirect()->route('imports.index')
                ->with('error', 'Error log not found or has expired (1-hour limit).');
        }

        return response()->stream(function () use ($errors) {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['#', 'Error Description']);
            foreach ($errors as $i => $error) {
                fputcsv($h, [$i + 1, $error]);
            }
            fclose($h);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="bulk_import_errors.csv"',
        ]);
    }

    public function index()
    {
        return view('imports.index');
    }
}
