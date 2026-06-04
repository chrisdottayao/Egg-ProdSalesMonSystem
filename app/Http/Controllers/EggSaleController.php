<?php

namespace App\Http\Controllers;

use App\Models\EggProduction;
use App\Models\EggSale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EggSaleController extends Controller
{
    public function index()
    {
        $sales = EggSale::latest('date')->paginate(20);

        // Build produced-by-date lookup for Sales Rate + Remaining columns
        $dates = $sales->pluck('date')->map(fn($d) => $d->format('Y-m-d'))->unique()->values();
        $producedByDate = EggProduction::whereIn(\DB::raw('DATE(date)'), $dates)
            ->selectRaw('DATE(date) as date_key, SUM(eggs_collected) as total')
            ->groupBy('date_key')
            ->pluck('total', 'date_key');

        return view('sales.index', compact('sales', 'producedByDate'));
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
            ['2024-01-01', 'Large',  160, 9.00, 'Regular customer'],
            ['2024-01-02', 'Large',  175, 9.00, ''],
            ['2024-01-03', 'Medium', 165, 9.00, 'Bulk order'],
            ['2024-01-04', 'Large',  180, 9.50, ''],
            ['2024-01-05', 'XL',     120, 10.00, 'Premium buyer'],
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
                $errors[] = "Row {$rowNumber} ({$date}): Invalid egg_size '{$data['egg_size']}'. Must be Peewee, Small, Medium, Large, XL, or Jumbo.";
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

            // Skip duplicate: same date + egg_size already exists
            if (EggSale::whereDate('date', $date)->where('egg_size', $eggSize)->exists()) {
                $skipped++;
                continue;
            }

            // Skip if quantity_sold > eggs_produced on that date
            $produced = EggProduction::whereDate('date', $date)->sum('eggs_collected');
            if ($produced > 0 && $quantity > $produced) {
                $errors[] = "Row {$rowNumber} ({$date}): quantity_sold ({$quantity}) exceeds eggs produced ({$produced}).";
                $failed++;
                continue;
            }

            EggSale::create([
                'date'           => $date,
                'egg_size'       => $eggSize,
                'quantity'       => $quantity,
                'price_per_unit' => $price,
                'total_amount'   => $quantity * $price,
                'notes'          => ($data['notes'] ?? '') !== '' ? $data['notes'] : null,
            ]);
            $imported++;
        }

        fclose($handle);

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
            default                     => null,
        };
    }
}
