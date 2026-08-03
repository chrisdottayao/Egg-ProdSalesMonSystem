<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CullRecord;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\HenBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EggProductionController extends Controller
{
    // TODO: calibrate if profiling on real hardware suggests a different size
    private const IMPORT_CHUNK_SIZE = 500;

    public function index()
    {
        $productions = EggProduction::latest('date')->paginate(20);
        $activeHens  = HenBatch::activeHenCount();
        return view('productions.index', compact('productions', 'activeHens'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'             => 'required|date',
            'eggs_collected'   => 'required|integer|min:0|max:10000',
            'active_hens'      => 'required|integer|min:1',
            'egg_size'         => 'required|string',
            'egg_weight'       => 'nullable|numeric|min:0',
            'mortality'        => 'required|integer|min:0',
            'spoilage_count'   => 'nullable|integer|min:0',
            'spoilage_reason'  => 'nullable|string',
            'notes'            => 'nullable|string',
        ]);

        $validated['spoilage_count'] = $validated['spoilage_count'] ?? 0;

        EggProduction::create($validated);

        return redirect()->route('productions.index')->with('success', 'Production entry saved successfully!');
    }

    public function edit(EggProduction $production)
    {
        return view('productions.edit', compact('production'));
    }

    public function update(Request $request, EggProduction $production)
    {
        $validated = $request->validate([
            'date'             => 'required|date',
            'eggs_collected'   => 'required|integer|min:0|max:10000',
            'active_hens'      => 'required|integer|min:1',
            'egg_size'         => 'required|string',
            'egg_weight'       => 'nullable|numeric|min:0',
            'mortality'        => 'required|integer|min:0',
            'spoilage_count'   => 'nullable|integer|min:0',
            'spoilage_reason'  => 'nullable|string',
            'notes'            => 'nullable|string',
        ]);

        $validated['spoilage_count'] = $validated['spoilage_count'] ?? 0;

        $production->update($validated);

        return redirect()->route('productions.index')->with('success', 'Production record updated.');
    }

    public function destroy(EggProduction $production)
    {
        $production->delete();
        return redirect()->route('productions.index')->with('success', 'Production record deleted.');
    }

    // ── Historical CSV Import ─────────────────────────────────────────────

    public function downloadTemplate()
    {
        $headers = [
            'date', 'eggs_collected', 'active_hens', 'egg_size', 'egg_weight',
            'mortality_count', 'eggs_sold', 'price_per_unit', 'culled_count',
            'cull_reason', 'notes',
        ];

        $rows = [
            ['2024-01-01', 182, 200, 'Large',  58.5, 0, 165, 9.00, 0, '',                 'SPC Farm — normal laying day'],
            ['2024-01-02', 176, 200, 'Large',  57.0, 1, 158, 9.00, 0, '',                 'SPC Farm — 1 mortality noted'],
            ['2024-01-03', 188, 199, 'Medium', 55.5, 0, 172, 9.00, 4, 'Age',              'SPC Farm — routine culling Batch A'],
            ['2024-01-04', 170, 195, 'Large',  59.0, 0, 150, 9.50, 0, '',                 'SPC Farm — price increase day'],
            ['2024-01-05', 185, 195, 'XL',     62.0, 2, 163, 9.00, 0, 'Health Condition', 'SPC Farm — 2 mortalities health-related'],
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
            'Content-Disposition' => 'attachment; filename="historical_import_template.csv"',
        ]);
    }

    public function importHistorical(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:10240']);

        $handle       = fopen($request->file('csv_file')->getPathname(), 'r');
        $headers      = null;
        $rowNumber    = 0;
        $stats        = [
            'prod_imported'  => 0, 'prod_skipped'  => 0,
            'sales_imported' => 0, 'sales_skipped' => 0,
            'cull_imported'  => 0, 'cull_skipped'  => 0,
            'errors'         => 0,
        ];
        $errors       = [];
        $defaultBatch = HenBatch::where('status', 'Active')->first();

        // ── Preload existing keys once, so duplicate checks don't hit the DB per row ──
        $existingProdDates = EggProduction::pluck('date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->flip()->all();

        $existingSaleKeys = EggSale::get(['date', 'egg_size'])
            ->map(fn ($s) => $s->date->format('Y-m-d') . '|' . $s->egg_size)
            ->flip()->all();

        $existingCullDates = CullRecord::pluck('date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->flip()->all();

        $prodRows     = [];
        $saleRows     = [];
        $saleDates    = []; // every date touched by an imported sale row, for the production_id backfill
        $cullRows     = [];

        DB::beginTransaction();

        try {
            while (($line = fgetcsv($handle)) !== false) {
                // Parse header row (first non-empty line)
                if ($headers === null) {
                    $headers    = array_map(fn($h) => strtolower(trim($h)), $line);
                    $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF"); // strip BOM
                    continue;
                }

                $rowNumber++;

                // Skip fully blank rows
                if (count(array_filter($line, fn($v) => trim($v) !== '')) === 0) {
                    continue;
                }

                // Pad short rows and combine with headers
                $line = array_pad($line, count($headers), '');
                $data = array_combine($headers, array_map('trim', array_slice($line, 0, count($headers))));

                // Validate date
                try {
                    $parsed = Carbon::parse($data['date'] ?? '');
                    // Reject obviously bad parses (Carbon parses empty string as now())
                    if (empty(trim($data['date'] ?? ''))) throw new \Exception('Empty');
                    $date = $parsed->format('Y-m-d');
                } catch (\Throwable) {
                    $errors[] = "Row {$rowNumber}: Invalid or missing date — '{$data['date']}'.";
                    $stats['errors']++;
                    continue;
                }

                $eggsCollected = (int) ($data['eggs_collected'] ?? 0);
                $activeHens    = (int) ($data['active_hens'] ?? 0);

                if ($eggsCollected <= 0 || $activeHens <= 0) {
                    $errors[] = "Row {$rowNumber} ({$date}): eggs_collected and active_hens must both be greater than zero.";
                    $stats['errors']++;
                    continue;
                }

                $eggSize = $this->mapEggSize($data['egg_size'] ?? '');

                // ── 1. egg_productions ───────────────────────────────────────────
                if (isset($existingProdDates[$date])) {
                    $stats['prod_skipped']++;
                } else {
                    $prodRows[] = [
                        'date'           => $date,
                        'eggs_collected' => $eggsCollected,
                        'active_hens'    => $activeHens,
                        'egg_size'       => $eggSize,
                        'egg_weight'     => is_numeric($data['egg_weight'] ?? '') && $data['egg_weight'] !== ''
                                                ? (float) $data['egg_weight'] : null,
                        'mortality'      => max(0, (int) ($data['mortality_count'] ?? 0)),
                        'notes'          => ($data['notes'] ?? '') !== '' ? $data['notes'] : null,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                    $existingProdDates[$date] = true; // catches duplicate dates within this same file
                    $stats['prod_imported']++;

                    if (count($prodRows) >= self::IMPORT_CHUNK_SIZE) {
                        $this->flushProductionBatch($prodRows);
                    }
                }

                // ── 2. egg_sales (only if eggs_sold > 0) ─────────────────────────
                $eggsSold = (int) ($data['eggs_sold'] ?? 0);
                if ($eggsSold > 0) {
                    $saleKey = $date . '|' . $eggSize;

                    if ($eggsSold > $eggsCollected) {
                        $errors[] = "Row {$rowNumber} ({$date}): eggs_sold ({$eggsSold}) exceeds eggs_collected ({$eggsCollected}) — sales row skipped.";
                        $stats['sales_skipped']++;
                    } elseif (isset($existingSaleKeys[$saleKey])) {
                        $stats['sales_skipped']++;
                    } else {
                        $price = is_numeric($data['price_per_unit'] ?? '') ? (float) $data['price_per_unit'] : 0.0;
                        $saleRows[] = [
                            'date'           => $date,
                            'egg_size'       => $eggSize,
                            'quantity'       => $eggsSold,
                            'price_per_unit' => $price,
                            'total_amount'   => $eggsSold * $price,
                            'production_id'  => null, // backfilled after import via a single join query
                            'notes'          => null,
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ];
                        $existingSaleKeys[$saleKey] = true;
                        $saleDates[$date]            = true;
                        $stats['sales_imported']++;

                        if (count($saleRows) >= self::IMPORT_CHUNK_SIZE) {
                            $this->flushSaleBatch($saleRows);
                        }
                    }
                }

                // ── 3. cull_records (only if culled_count > 0) ───────────────────
                $culledCount = (int) ($data['culled_count'] ?? 0);
                if ($culledCount > 0) {
                    if (isset($existingCullDates[$date])) {
                        $stats['cull_skipped']++;
                    } elseif (! $defaultBatch) {
                        $errors[] = "Row {$rowNumber} ({$date}): No active hen batch found — cull record skipped.";
                        $stats['cull_skipped']++;
                    } else {
                        $cullRows[] = [
                            'date'            => $date,
                            'hen_batch_id'    => $defaultBatch->id,
                            'quantity_culled' => $culledCount,
                            'reason'          => $this->mapCullReason($data['cull_reason'] ?? ''),
                            'notes'           => null,
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ];
                        $existingCullDates[$date] = true;
                        $stats['cull_imported']++;

                        if (count($cullRows) >= self::IMPORT_CHUNK_SIZE) {
                            $this->flushCullBatch($cullRows);
                        }
                    }
                }
            }

            fclose($handle);

            $this->flushProductionBatch($prodRows);
            $this->flushSaleBatch($saleRows);
            $this->flushCullBatch($cullRows);

            // ── Backfill egg_sales.production_id for the dates touched by this import ──
            if (! empty($saleDates)) {
                DB::table('egg_sales')
                    ->join('egg_productions', function ($join) {
                        $join->on('egg_productions.date', '=', 'egg_sales.date')
                             ->on('egg_productions.egg_size', '=', 'egg_sales.egg_size');
                    })
                    ->whereNull('egg_sales.production_id')
                    ->whereIn('egg_sales.date', array_keys($saleDates))
                    ->update(['egg_sales.production_id' => DB::raw('egg_productions.id')]);
            }

            // Bulk import bypasses per-row model events, so record one summary
            // audit entry instead of thousands of per-row ones.
            AuditLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'import',
                'model_type' => 'HistoricalCsvImport',
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

        // Store error log in cache for 1 hour (user can download it)
        $errorToken = null;
        if (! empty($errors)) {
            $errorToken = Str::uuid()->toString();
            Cache::put("import_errors_{$errorToken}", $errors, now()->addHour());
        }

        return redirect()->route('productions.index')->with([
            'import_summary'     => $stats,
            'import_error_token' => $errorToken,
        ]);
    }

    private function flushProductionBatch(array &$rows): void
    {
        if (empty($rows)) return;

        EggProduction::insert($rows);

        $rows = [];
    }

    private function flushSaleBatch(array &$rows): void
    {
        if (empty($rows)) return;

        EggSale::insert($rows);

        $rows = [];
    }

    private function flushCullBatch(array &$rows): void
    {
        if (empty($rows)) return;

        CullRecord::insert($rows);

        $rows = [];
    }

    public function downloadImportErrors(string $token)
    {
        $errors = Cache::get("import_errors_{$token}");

        if (! $errors) {
            return redirect()->route('productions.index')
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
            'Content-Disposition' => 'attachment; filename="import_errors.csv"',
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function mapEggSize(string $input): string
    {
        return match (strtolower(trim($input))) {
            'peewee'                     => 'Peewee',
            'small'                      => 'Small',
            'medium'                     => 'Medium',
            'xl', 'extra large', 'x-l'  => 'XL',
            'jumbo'                      => 'Jumbo',
            default                      => 'Large',
        };
    }

    private function mapCullReason(string $input): string
    {
        return match (strtolower(trim($input))) {
            'age'                           => 'Age',
            'low productivity', 'low prod'  => 'Low Productivity',
            'health condition', 'health'    => 'Health Condition',
            default                         => 'Other',
        };
    }
}
