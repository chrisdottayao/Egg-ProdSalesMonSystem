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
        Schema::table('anomaly_alerts', function (Blueprint $table) {
            $table->dropUnique(['type', 'alert_date', 'hen_batch_id']);
            $table->dropForeign(['hen_batch_id']);
            $table->dropColumn(['hen_batch_id', 'expected_rate', 'deviation', 'cluster_id']);
            $table->decimal('deviation_pct', 6, 2)->nullable(false)->change();

            $table->unique(['type', 'alert_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('anomaly_alerts', function (Blueprint $table) {
            $table->dropUnique(['type', 'alert_date']);

            $table->foreignId('hen_batch_id')->nullable()->after('id')->constrained('hen_batches')->nullOnDelete();
            $table->decimal('expected_rate', 5, 2)->nullable()->after('deviation_pct');
            $table->decimal('deviation', 6, 2)->nullable()->after('expected_rate');
            $table->string('cluster_id')->nullable()->after('deviation');
            $table->decimal('deviation_pct', 6, 2)->nullable()->change();

            $table->unique(['type', 'alert_date', 'hen_batch_id']);
        });
    }
};
