<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('building_daily', function (Blueprint $table) {
            // Nullable: historical bulk-imported rows have no real contributor —
            // left null rather than attributed to whoever happened to run the import.
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();

            // 'date' already has a leftmost-prefix index via the existing
            // unique(date, hen_batch_id) constraint, but a dedicated one keeps this
            // explicit and matches the manuscript's claim of real indexing per column.
            $table->index('date');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('building_daily', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->dropIndex(['user_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
