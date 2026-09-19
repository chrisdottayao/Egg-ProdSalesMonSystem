<?php

namespace Database\Seeders;

use App\Models\Expense;
use Illuminate\Database\Seeder;

class ExpenseReceiptSeeder extends Seeder
{
    /**
     * Verified farm receipts — all farm-wide (building_id null). Aminovit and
     * Electrocare are ongoing weekly regimens (Mon / Thu respectively); these
     * rows are the periodic reorders, flagged is_recurring so the summary
     * page treats them as an ongoing monthly cost line rather than a
     * one-off purchase.
     */
    public function run(): void
    {
        $rows = [
            ['date' => '2026-09-18', 'category' => 'vaccine',  'description' => 'Nobilis MA5+Clone 30 SPH 5000DS',  'quantity' => 4,  'unit' => 'bottle', 'unit_price' => 969.00,   'amount' => 3876.00,  'supplier' => 'RMY Marketing'],
            ['date' => '2026-09-18', 'category' => 'vaccine',  'description' => 'Nobilis Gumboro 228E SPH 1000DS',  'quantity' => 12, 'unit' => 'bottle', 'unit_price' => 646.00,   'amount' => 7752.00,  'supplier' => 'RMY Marketing'],
            ['date' => '2026-09-18', 'category' => 'vaccine',  'description' => 'Nobilis MG 6/85 1000DS',           'quantity' => 16, 'unit' => 'bottle', 'unit_price' => 4516.00,  'amount' => 72256.00, 'supplier' => 'RMY Marketing'],
            ['date' => '2026-09-18', 'category' => 'vaccine',  'description' => 'Hipraviar SHS 1000DS',             'quantity' => 24, 'unit' => 'bottle', 'unit_price' => 520.00,   'amount' => 12480.00, 'supplier' => 'RMY Marketing'],
            ['date' => '2026-09-18', 'category' => 'vaccine',  'description' => 'Hipragumboro GM97 1000DS',         'quantity' => 12, 'unit' => 'bottle', 'unit_price' => 335.00,   'amount' => 4020.00,  'supplier' => 'RMY Marketing'],
            ['date' => '2026-09-18', 'category' => 'vaccine',  'description' => 'Volvac AC+Emul Bacterin 1000DS',   'quantity' => 12, 'unit' => 'bottle', 'unit_price' => 1201.39,  'amount' => 14416.68, 'supplier' => 'RMY Marketing'],
            ['date' => '2025-05-08', 'category' => 'vitamins', 'description' => 'Electrocare+ 15kg',                'quantity' => 5,  'unit' => 'sack',   'unit_price' => 4800.00,  'amount' => 24000.00, 'supplier' => 'Inphilco',   'is_recurring' => true, 'recurrence' => 'weekly-Thu'],
            ['date' => '2025-08-16', 'category' => 'vitamins', 'description' => 'Trovite 5kg',                     'quantity' => 10, 'unit' => 'pail',   'unit_price' => 2300.00,  'amount' => 23000.00, 'supplier' => 'ALC Trading'],
            ['date' => '2025-06-27', 'category' => 'vitamins', 'description' => 'APSA Aminovit 1L',                'quantity' => 50, 'unit' => 'bottle', 'unit_price' => 540.00,   'amount' => 27000.00, 'supplier' => 'Apsavet',    'is_recurring' => true, 'recurrence' => 'weekly-Mon'],
        ];

        $written = 0;

        foreach ($rows as $row) {
            $exists = Expense::whereNull('building_id')
                ->whereDate('date', $row['date'])
                ->where('description', $row['description'])
                ->exists();

            if ($exists) {
                continue;
            }

            Expense::create(array_merge([
                'building_id'  => null,
                'is_estimated' => false,
                'is_recurring' => false,
                'recurrence'   => null,
            ], $row));

            $written++;
        }

        $this->command?->info("ExpenseReceiptSeeder: {$written} row(s) written, " . (count($rows) - $written) . ' already present.');
    }
}
