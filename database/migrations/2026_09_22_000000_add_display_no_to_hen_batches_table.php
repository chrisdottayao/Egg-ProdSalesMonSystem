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
        // Separate from building_no (the real farm house number, 1-45) rather
        // than overwriting it: building_no has no unique constraint, but the
        // production database almost certainly already has real, untracked
        // buildings numbered 1, 2, and 3 among the other 42 — overwriting
        // building_no for the 3 tracked buildings would silently create two
        // rows both claiming the same real number, with nothing in the schema
        // to catch it. display_no is purely a UI relabeling layer; building_no
        // keeps the real farm identity intact for internal matching (CSV
        // import, physical-adjacency clustering) and manuscript reference.
        Schema::table('hen_batches', function (Blueprint $table) {
            $table->unsignedTinyInteger('display_no')->nullable()->after('is_tracked');
        });

        // The 3M mapping, made permanent here (Prototype 3N — the person asked
        // for the 3 tracked buildings to simply read "Building 1/2/3" in the
        // UI, with staff briefed on the real mapping directly):
        //   building_no 4  (real farm building 4)  -> display_no 1
        //   building_no 14 (real farm building 14) -> display_no 2
        //   building_no 45 (real farm building 45) -> display_no 3
        DB::table('hen_batches')->where('building_no', 4)->update(['display_no' => 1]);
        DB::table('hen_batches')->where('building_no', 14)->update(['display_no' => 2]);
        DB::table('hen_batches')->where('building_no', 45)->update(['display_no' => 3]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hen_batches', function (Blueprint $table) {
            $table->dropColumn('display_no');
        });
    }
};
