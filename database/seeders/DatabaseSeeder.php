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
        $batch1 = HenBatch::updateOrCreate(['batch_id' => 'HB-2024-001'], [
            'batch_size' => 200,
            'status'     => 'Active',
            'entry_date' => '2024-01-15',
            'notes'      => 'Layer hens — Batch A',
        ]);
        HenBatch::updateOrCreate(['batch_id' => 'HB-2024-002'], [
            'batch_size' => 0,
            'status'     => 'Culled',
            'entry_date' => '2024-06-01',
            'notes'      => 'Layer hens — Batch B (culled)',
        ]);

        // ── Cattle Records ─────────────────────────────────────
        foreach ([
            ['ear_tag' => 'CT-001', 'status' => 'Active',   'entry_date' => '2023-03-10', 'notes' => 'Bull'],
            ['ear_tag' => 'CT-002', 'status' => 'Active',   'entry_date' => '2023-05-20', 'notes' => 'Cow'],
            ['ear_tag' => 'CT-003', 'status' => 'Sold',     'entry_date' => '2023-08-01', 'notes' => 'Sold Oct 2024'],
            ['ear_tag' => 'CT-004', 'status' => 'Active',   'entry_date' => '2024-01-05', 'notes' => 'Heifer'],
            ['ear_tag' => 'CT-005', 'status' => 'Deceased', 'entry_date' => '2024-04-12', 'notes' => 'Natural death'],
        ] as $c) {
            CattleRecord::firstOrCreate(['ear_tag' => $c['ear_tag']], $c);
        }

        // ── Egg Production & Sales (61 days: 60 days ago → today) ────
        for ($day = 0; $day <= 60; $day++) {
            $daysAgo    = 60 - $day; // day 0 = 60 days ago, day 60 = today
            $date       = Carbon::today()->subDays($daysAgo)->format('Y-m-d');
            $activeHens = 200;

            $mortality     = rand(1, 10) <= 2 ? rand(1, 2) : 0;
            $eggSize       = rand(1, 10) <= 3 ? 'Medium' : 'Large';
            $eggsCollected = rand(170, 190);

            EggProduction::updateOrCreate(
                ['date' => $date],
                [
                    'eggs_collected' => $eggsCollected,
                    'active_hens'    => $activeHens,
                    'egg_size'       => $eggSize,
                    'egg_weight'     => round(rand(55, 70) / 10, 1),
                    'mortality'      => $mortality,
                    'notes'          => null,
                ]
            );

            $qtySold = $eggsCollected - rand(10, 20);
            EggSale::updateOrCreate(
                ['date' => $date],
                [
                    'egg_size'       => $eggSize,
                    'quantity'       => $qtySold,
                    'price_per_unit' => 9.00,
                    'total_amount'   => $qtySold * 9.00,
                    'notes'          => null,
                ]
            );
        }

        // ── Cull Records ───────────────────────────────────────
        foreach ([
            [Carbon::today()->subDays(50)->format('Y-m-d'), 'Age'],
            [Carbon::today()->subDays(30)->format('Y-m-d'), 'Low Productivity'],
            [Carbon::today()->subDays(10)->format('Y-m-d'), 'Health Condition'],
        ] as [$cullDate, $reason]) {
            if (! CullRecord::whereDate('date', $cullDate)->exists()) {
                CullRecord::create([
                    'date'            => $cullDate,
                    'hen_batch_id'    => $batch1->id,
                    'quantity_culled' => rand(3, 8),
                    'reason'          => $reason,
                    'notes'           => 'Routine culling',
                ]);
            }
        }
    }
}
