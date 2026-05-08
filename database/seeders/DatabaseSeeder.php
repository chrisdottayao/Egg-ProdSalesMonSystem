<?php

namespace Database\Seeders;

use App\Models\CattleRecord;
use App\Models\CullRecord;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\HenBatch;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ── Users ──────────────────────────────────────────────
        User::firstOrCreate(['email' => 'admin@spcfarm.com'], [
            'name'     => 'Farm Admin',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);
        User::firstOrCreate(['email' => 'manager@spcfarm.com'], [
            'name'     => 'Farm Manager',
            'password' => Hash::make('password'),
            'role'     => 'manager',
        ]);
        User::firstOrCreate(['email' => 'staff@spcfarm.com'], [
            'name'     => 'Farm Staff',
            'password' => Hash::make('password'),
            'role'     => 'staff',
        ]);

        // ── Hen Batches ────────────────────────────────────────
        $batch1 = HenBatch::firstOrCreate(['batch_id' => 'HB-2024-001'], [
            'batch_size' => 300,
            'status'     => 'Active',
            'entry_date' => '2024-01-15',
            'notes'      => 'Layer hens — Batch A',
        ]);
        $batch2 = HenBatch::firstOrCreate(['batch_id' => 'HB-2024-002'], [
            'batch_size' => 200,
            'status'     => 'Active',
            'entry_date' => '2024-06-01',
            'notes'      => 'Layer hens — Batch B',
        ]);

        // ── Cattle Records ─────────────────────────────────────
        $cattle = [
            ['ear_tag' => 'CT-001', 'status' => 'Active',   'entry_date' => '2023-03-10', 'notes' => 'Bull'],
            ['ear_tag' => 'CT-002', 'status' => 'Active',   'entry_date' => '2023-05-20', 'notes' => 'Cow'],
            ['ear_tag' => 'CT-003', 'status' => 'Sold',     'entry_date' => '2023-08-01', 'notes' => 'Sold Oct 2024'],
            ['ear_tag' => 'CT-004', 'status' => 'Active',   'entry_date' => '2024-01-05', 'notes' => 'Heifer'],
            ['ear_tag' => 'CT-005', 'status' => 'Deceased', 'entry_date' => '2024-04-12', 'notes' => 'Natural death'],
        ];
        foreach ($cattle as $c) {
            CattleRecord::firstOrCreate(['ear_tag' => $c['ear_tag']], $c);
        }

        // ── Egg Production (last 30 days) ──────────────────────
        $sizes = ['Medium', 'Large', 'XL'];
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i)->format('Y-m-d');

            // Skip if already seeded
            if (EggProduction::whereDate('date', $date)->exists()) continue;

            // Simulate realistic production with an anomaly on day 10
            $baseEggs  = rand(420, 480);
            $mortality = rand(0, 2);
            if ($i === 10) $baseEggs = 290; // anomaly — production drop

            EggProduction::create([
                'date'           => $date,
                'eggs_collected' => $baseEggs,
                'active_hens'    => 500,
                'egg_size'       => $sizes[array_rand($sizes)],
                'egg_weight'     => round(rand(55, 70) / 10, 1),
                'mortality'      => $mortality,
                'notes'          => $mortality > 0 ? "{$mortality} mortality recorded" : null,
            ]);

            // Egg sales (sell ~85% of production)
            $sold  = (int) round($baseEggs * (rand(80, 90) / 100));
            $price = rand(7, 9) + 0.00;

            EggSale::create([
                'date'           => $date,
                'egg_size'       => $sizes[array_rand($sizes)],
                'quantity'       => $sold,
                'price_per_unit' => $price,
                'total_amount'   => $sold * $price,
                'notes'          => null,
            ]);
        }

        // ── Cull Records ───────────────────────────────────────
        $reasons = ['Age', 'Low Productivity', 'Health Condition'];
        $cullDates = [
            Carbon::today()->subDays(25)->format('Y-m-d'),
            Carbon::today()->subDays(12)->format('Y-m-d'),
            Carbon::today()->subDays(3)->format('Y-m-d'),
        ];
        foreach ($cullDates as $idx => $cullDate) {
            if (!CullRecord::whereDate('date', $cullDate)->exists()) {
                CullRecord::create([
                    'date'            => $cullDate,
                    'hen_batch_id'    => $batch1->id,
                    'quantity_culled' => rand(3, 8),
                    'reason'          => $reasons[$idx % count($reasons)],
                    'notes'           => 'Routine culling',
                ]);
            }
        }
    }
}
