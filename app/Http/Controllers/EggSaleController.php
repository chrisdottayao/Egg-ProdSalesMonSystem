<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\EggGradingDaily;
use App\Models\EggProduction;
use App\Models\EggSale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EggSaleController extends Controller
{
    private const IMPORT_CHUNK_SIZE = 500;

    public function index()
    {
        $sales = EggSale::latest('date')->paginate(20);

        // Sales Rate / Remaining must compare each row against THAT SIZE's own
        // daily production (egg_grading_daily), not the whole farm's total —
        // otherwise a low-volume size like Jumbo reads as near-0% against the
        // ~69k farm-wide figure even at full sell-through.
        $dates = $sales->pluck('date')->map(fn($d) => $d->format('Y-m-d'))->unique()->values();
        $gradingBySizeAndDate = EggGradingDaily::whereIn('date', $dates)
            ->get()
            ->groupBy(fn($row) => $row->date->format('Y-m-d'))
            ->map(fn($rows) => $rows->pluck('total_pcs', 'category'));

        // Keyed by "date|egg_size" using egg_sales' OWN size vocabulary (so the
        // view never has to know that grading calls XL "XLarge" instead) —
        // the only category name that differs between the two tables.
        $producedBySizeAndDate = [];
        foreach ($sales as $sale) {
            $dateKey = $sale->date->format('Y-m-d');
            $category = $sale->egg_size === 'XL' ? 'XLarge' : $sale->egg_size;
            $key = "{$dateKey}|{$sale->egg_size}";
            $producedBySizeAndDate[$key] = $gradingBySizeAndDate[$dateKey][$category] ?? null;
        }

        return view('sales.index', compact('sales', 'producedBySizeAndDate'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'           => 'required|date',
            'egg_size'       => 'required|string',
            'quantity'       => 'required|integer|min:1',
            'price_per_unit' => 'required|numeric|min:0',
            'notes'          => 'nullable|string',
        ]);

        // Hard block: sold > produced on that date
        $produced = EggProduction::whereDate('date', $validated['date'])->sum('eggs_collected');
        if ($produced > 0 && $validated['quantity'] > $produced) {
            return back()->withInput()->with(
                'hard_block',
                "Entry blocked: Quantity sold ({$validated['quantity']}) exceeds eggs produced on {$validated['date']} ({$produced}). Correct the entry before saving."
            );
        }

        $validated['total_amount'] = $validated['quantity'] * $validated['price_per_unit'];

        EggSale::create($validated);

        return redirect()->route('sales.index')->with('success', 'Sales entry saved successfully!');
    }

    public function edit(EggSale $sale)
    {
        return view('sales.edit', compact('sale'));
    }

    public function update(Request $request, EggSale $sale)
    {
        $validated = $request->validate([
            'date'           => 'required|date',
            'egg_size'       => 'required|string',
            'quantity'       => 'required|integer|min:1',
            'price_per_unit' => 'required|numeric|min:0',
            'notes'          => 'nullable|string',
        ]);

        $validated['total_amount'] = $validated['quantity'] * $validated['price_per_unit'];
        $sale->update($validated);

        return redirect()->route('sales.index')->with('success', 'Sales record updated.');
    }

    public function destroy(EggSale $sale)
    {
        $sale->delete();
        return redirect()->route('sales.index')->with('success', 'Sales record deleted.');
    }

    // ── Sales CSV Import ──────────────────────────────────────────────────

    public function downloadSalesTemplate()
    {
        $headers = ['date', 'egg_size', 'quantity_sold', 'price_per_unit', 'notes'];

        $rows = [
            ['2024-01-01', 'Large',     160, 9.00, 'Regular customer'],
            ['2024-01-02', 'Large',     175, 9.00, ''],
            ['2024-01-03', 'Medium',    165, 9.00, 'Bulk order'],
            ['2024-01-04', 'Large',     180, 9.50, ''],
            ['2024-01-05', 'XL',        120, 10.00, 'Premium buyer'],
            ['2024-01-06', 'No Value',  20,  3.00, 'Sold at discount'],
            ['2024-01-06', 'No Weight', 15,  3.00, 'Sold at discount'],
            ['2024-01-06', 'Dirty',     10,  2.50, 'Sold at discount'],
            ['2024-01-06', 'Broken',    8,   2.00, 'Sold at discount'],
            ['2024-01-06', 'Waste',     5,   1.50, 'Sold at discount'],
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
            'Content-Disposition' => 'attachment; filename="sales_import_template.csv"',
        ]);
    }

    public function importSales(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:10240']);

        $handle    = fopen($request->file('csv_file')->getPathname(), 'r');
        $headers   = null;
        $rowNumber = 0;
        $imported  = 0;
        $skipped   = 0;
        $failed    = 0;
        $errors    = [];

        // ── Pass 1: parse every row into memory, collecting distinct dates ──────
        // Needed so the "quantity_sold > eggs_produced" check can be backed by a
        // single preloaded lookup instead of one query per row.
        $parsedRows    = [];
        $distinctDates = [];

        while (($line = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers    = array_map(fn($h) => strtolower(trim($h)), $line);
                $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
                continue;
            }

            $rowNumber++;

            if (count(array_filter($line, fn($v) => trim($v) !== '')) === 0) {
                continue;
            }

            $line = array_pad($line, count($headers), '');
            $data = array_combine($headers, array_map('trim', array_slice($line, 0, count($headers))));

            // Validate date
            try {
                if (empty($data['date'] ?? '')) throw new \Exception('Empty date');
                $date = Carbon::parse($data['date'])->format('Y-m-d');
            } catch (\Throwable) {
                $errors[] = "Row {$rowNumber}: Invalid date '{$data['date']}'.";
                $failed++;
                continue;
            }

            // Validate egg_size
            $eggSize = $this->resolveEggSize($data['egg_size'] ?? '');
            if ($eggSize === null) {
                $errors[] = "Row {$rowNumber} ({$date}): Invalid egg_size '{$data['egg_size']}'. Must be Peewee, Small, Medium, Large, XL, Jumbo, No Value, No Weight, Dirty, Broken, or Waste.";
                $failed++;
                continue;
            }

            // Validate quantity_sold
            $quantity = (int) ($data['quantity_sold'] ?? 0);
            if ($quantity <= 0) {
                $errors[] = "Row {$rowNumber} ({$date}): quantity_sold must be a positive integer.";
                $failed++;
                continue;
            }

            // Validate price_per_unit
            $price = (float) ($data['price_per_unit'] ?? 0);
            if ($price <= 0) {
                $errors[] = "Row {$rowNumber} ({$date}): price_per_unit must be a positive number.";
                $failed++;
                continue;
            }

            $parsedRows[] = [
                'row_number' => $rowNumber,
                'date'       => $date,
                'egg_size'   => $eggSize,
                'quantity'   => $quantity,
                'price'      => $price,
                'notes'      => ($data['notes'] ?? '') !== '' ? $data['notes'] : null,
            ];
            $distinctDates[$date] = true;
        }
        fclose($handle);

        // ── Preload existing (date, egg_size) keys — no per-row exists() query ──
        $existingKeys = EggSale::get(['date', 'egg_size'])
            ->map(fn($s) => $s->date->format('Y-m-d') . '|' . $s->egg_size)
            ->flip()->all();

        // ── Preload eggs_collected totals for every date touched by this file ───
        $producedByDate = EggProduction::whereIn(DB::raw('DATE(date)'), array_keys($distinctDates))
            ->selectRaw('DATE(date) as date_key, SUM(eggs_collected) as total')
            ->groupBy('date_key')
            ->pluck('total', 'date_key');

        $insertRows  = [];
        $saleDates   = [];

        DB::beginTransaction();

        try {
            foreach ($parsedRows as $row) {
                $key = $row['date'] . '|' . $row['egg_size'];

                // Skip duplicate: same date + egg_size already exists
                if (isset($existingKeys[$key])) {
                    $skipped++;
                    continue;
                }

                // Skip if quantity_sold > eggs_produced on that date
                $produced = (int) ($producedByDate[$row['date']] ?? 0);
                if ($produced > 0 && $row['quantity'] > $produced) {
                    $errors[] = "Row {$row['row_number']} ({$row['date']}): quantity_sold ({$row['quantity']}) exceeds eggs produced ({$produced}).";
                    $failed++;
                    continue;
                }

                $insertRows[] = [
                    'date'           => $row['date'],
                    'egg_size'       => $row['egg_size'],
                    'quantity'       => $row['quantity'],
                    'price_per_unit' => $row['price'],
                    'total_amount'   => $row['quantity'] * $row['price'],
                    'production_id'  => null, // backfilled after import via a single join query
                    'notes'          => $row['notes'],
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
                $existingKeys[$key]    = true;
                $saleDates[$row['date']] = true;
                $imported++;

                if (count($insertRows) >= self::IMPORT_CHUNK_SIZE) {
                    // insertOrIgnore, not insert: a real DB-level unique
                    // constraint on (date, egg_size) means a second overlapping
                    // run of this same import can't land duplicate rows even if
                    // it raced past the in-memory check above.
                    EggSale::insertOrIgnore($insertRows);
                    $insertRows = [];
                }
            }

            if (! empty($insertRows)) {
                EggSale::insertOrIgnore($insertRows);
            }

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

            // Bulk insert bypasses EggSale's creating/created model events, so
            // record one summary audit entry instead of thousands of per-row ones.
            AuditLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'import',
                'model_type' => 'SalesCsvImport',
                'model_id'   => null,
                'details'    => compact('imported', 'skipped', 'failed'),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $errorToken = null;
        if (! empty($errors)) {
            $errorToken = Str::uuid()->toString();
            Cache::put("sales_import_errors_{$errorToken}", $errors, now()->addHour());
        }

        return redirect()->route('sales.index')->with([
            'import_summary' => compact('imported', 'skipped', 'failed'),
            'import_error_token' => $errorToken,
        ]);
    }

    public function downloadSalesImportErrors(string $token)
    {
        $errors = Cache::get("sales_import_errors_{$token}");

        if (! $errors) {
            return redirect()->route('sales.index')
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
            'Content-Disposition' => 'attachment; filename="sales_import_errors.csv"',
        ]);
    }

    private function resolveEggSize(string $input): ?string
    {
        return match (strtolower(trim($input))) {
            'peewee'                    => 'Peewee',
            'small'                     => 'Small',
            'medium'                    => 'Medium',
            'large'                     => 'Large',
            'xl', 'extra large', 'x-l' => 'XL',
            'jumbo'                     => 'Jumbo',
            'no value'                  => 'No Value',
            'no weight'                 => 'No Weight',
            'dirty'                     => 'Dirty',
            'broken'                    => 'Broken',
            'waste'                     => 'Waste',
            default                     => null,
        };
    }
}
