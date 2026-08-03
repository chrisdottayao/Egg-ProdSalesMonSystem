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
        Schema::table('forecast_evaluations', function (Blueprint $table) {
            // MAPE of the original single-feature (day-index only) model, kept
            // alongside 'mape' (now the two-feature day+age model) so the
            // improvement from adding flock age is documented, not just claimed.
            $table->decimal('mape_before_age_feature', 8, 4)->nullable()->after('mape');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forecast_evaluations', function (Blueprint $table) {
            $table->dropColumn('mape_before_age_feature');
        });
    }
};
