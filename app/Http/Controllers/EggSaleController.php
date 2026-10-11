<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\EggGradingDaily;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Support\EggPrices;
use App\Support\LatestEntry;
use App\Support\SoldVsProduced;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EggSaleController extends Controller
{
    private const IMPORT_CHUNK_SIZE = 500;

    public function index(Request $request)
    {
        // History: approved rows plus, for staff, their own pending/rejected
        // ones; admin/manager see everything (Phase 3 item 1, 3B item 6).
        $sales = EggSale::visibleTo($request->user())->with(['enteredBy', 'approver'])->latest('date')->paginate(20);

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

        $latest = LatestEntry::for(EggSale::class);

        // S13: set prices per size, for the form's price auto-fill.
        $eggPrices = EggPrices::all();

        // S24: the latest sold > produced flag for each sale on this page.
        $soldFlags = AuditLog::with('resolver')
            ->where('model_type', 'EggSale')->where('inconsistency_rule', SoldVsProduced::RULE)
            ->whereIn('model_id', $sales->pluck('id'))
            ->orderBy('id')->get()->keyBy('model_id');

        return view('sales.index', compact('sales', 'producedBySizeAndDate', 'latest', 'eggPrices', 'soldFlags'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'           => $this->entryDateRules(true, $request->input('queued_at')),
            'egg_size'       => 'required|string',
            'quantity'       => 'required|integer|min:1',
            'price_per_unit' => 'nullable|numeric|min:0',
            'notes'          => 'nullable|string',
        ]);

        // S13: a blank price takes the admin-set price for that size.
        $validated['price_per_unit'] = EggPrices::priceOrDefault($validated['price_per_unit'] ?? null, $validated['egg_size']);
        if ($validated['price_per_unit'] === null) {
            return back()->withInput()->withErrors(['price_per_unit' => 'Enter a price — no set price exists for this egg size.']);
        }

        // S24: sold > produced is a warning, not a block (stock may carry over).
        $soldCheck = SoldVsProduced::check($validated['date'], (int) $validated['quantity']);
        if ($response = $this->requireSoldConfirmation($request, $soldCheck)) {
            return $response;
        }

        $validated['total_amount'] = $validated['quantity'] * $validated['price_per_unit'];
        $validated['entered_by']   = $request->user()->id;

        // Phase 3 item 1: staff sales wait for manager/admin approval.
        $sale = EggSale::create($validated + EggSale::approvalFieldsFor($request->user()));
        $this->flagSoldOverProduced($request, $sale, $soldCheck);

        return redirect()->route('sales.index')
            ->with('success', $sale->isPending()
                ? 'Sales entry saved. It is waiting for a manager or admin to approve it before it counts in totals.'
                : 'Sales entry saved successfully!')
            ->with('latest_ids', [$sale->id]);
    }

    public function edit(EggSale $sale)
    {
        $this->denyStaffChange($sale);

        return view('sales.edit', compact('sale'));
    }

    public function update(Request $request, EggSale $sale)
    {
        $this->denyStaffChange($sale);

        $validated = $request->validate([
            'date'           => $this->editDateRules($sale),
            'egg_size'       => 'required|string',
            'quantity'       => 'required|integer|min:1',
            'price_per_unit' => 'required|numeric|min:0',
            'notes'          => 'nullable|string',
        ]);

        $soldCheck = SoldVsProduced::check(Carbon::parse($validated['date'])->toDateString(), (int) $validated['quantity'], $sale);
        if ($response = $this->requireSoldConfirmation($request, $soldCheck)) {
            return $response;
        }

        $validated['total_amount'] = $validated['quantity'] * $validated['price_per_unit'];
        // A staff edit (including resubmitting a rejected sale) goes back to
        // pending; an admin/manager edit is approved by them.
        $sale->update($validated + EggSale::approvalFieldsFor($request->user()));
        $this->flagSoldOverProduced($request, $sale, $soldCheck);

        return redirect()->route('sales.index')->with('success', $sale->isPending()
            ? 'Sales record updated and sent for approval.'
            : 'Sales record updated.');
    }

    public function destroy(EggSale $sale)
    {
        $this->denyStaffChange($sale);

        $sale->delete();
        return redirect()->route('sales.index')->with('success', 'Sales record deleted.');
    }

    /**
     * S24: send the user back with a warning until they tick "Continue".
     * An offline-queued entry (queued_at) is never bounced — the PWA sync
     * treats any redirect as success and would drop it — it is saved and
     * flagged as unconfirmed instead.
     */
    private function requireSoldConfirmation(Request $request, ?array $soldCheck)
    {
        if (! $soldCheck || $request->boolean('confirm_sold_gt_produced') || $request->filled('queued_at')) {
            return null;
        }

        return back()->withInput()->with('sold_gt_produced', $soldCheck + ['message' => SoldVsProduced::message($soldCheck)]);
    }

    private function flagSoldOverProduced(Request $request, EggSale $sale, ?array $soldCheck): void
    {
        if (! $soldCheck) {
            return;
        }

        AuditLog::create([
            'user_id'               => $request->user()->id,
            'action'                => 'sold_gt_produced',
            'model_type'            => 'EggSale',
            'model_id'              => $sale->id,
            'details'               => $soldCheck + [
                'egg_size'  => $sale->egg_size,
                'confirmed' => $request->boolean('confirm_sold_gt_produced') ? 'yes' : 'no (synced from offline)',
            ],
            'inconsistency_flagged' => true,
            'inconsistency_rule'    => SoldVsProduced::RULE,
        ]);
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

            // Validate price_per_unit. S13: a blank price uses the admin-set
            // price for that size; a price in the CSV is always kept.
            $price = (float) EggPrices::priceOrDefault($data['price_per_unit'] ?? null, $eggSize);
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
        $existingKeys = EggSale::withPending()->get(['date', 'egg_size'])
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
                    // Imports are admin/manager only, so rows land approved by
                    // the importer; a staff import would land pending (3B).
                    ...EggSale::entryFieldsFor(auth()->user()),
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
