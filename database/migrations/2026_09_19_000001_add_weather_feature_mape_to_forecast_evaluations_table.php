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
            // Mirrors mape_before_age_feature: MAPE just before weather features
            // (temp_mean/thi/precipitation) are added, kept alongside 'mape' (now
            // the model including whichever features are active) so the effect of
            // weather is documented, not just claimed.
            $table->decimal('mape_before_weather_feature', 8, 4)->nullable()->after('mape_before_age_feature');
            $table->boolean('weather_feature_active')->default(false)->after('mape_before_weather_feature');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forecast_evaluations', function (Blueprint $table) {
            $table->dropColumn(['mape_before_weather_feature', 'weather_feature_active']);
        });
    }
};
