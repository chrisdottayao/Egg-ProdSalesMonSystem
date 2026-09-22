<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hen_batches', function (Blueprint $table) {
            // Scope flag (IT expert recommendation, Prototype 3M): the live
            // system actively records/displays only 3 of the farm's 45 real
            // buildings going forward. false = "not shown," never "deleted" —
            // every existing BuildingDaily/EggProduction/EggSale/CullRecord/
            // Expense/FlockAlert/AnomalyAlert row for the other 42 buildings
            // stays exactly as it is.
            $table->boolean('is_tracked')->default(false)->after('building_no');
        });

        // Buildings 4, 14, 45 — chosen from the farm's actual September 2026
        // logbook (not arbitrary): a real peer-performance gap (4 vs 8), a
        // building already past the 140-week cull target (14), and a healthy
        // young flock (45). See the Prototype 3M spec for the full rationale.
        DB::table('hen_batches')
            ->whereIn('building_no', [4, 14, 45])
            ->update(['is_tracked' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hen_batches', function (Blueprint $table) {
            $table->dropColumn('is_tracked');
        });
    }
};
