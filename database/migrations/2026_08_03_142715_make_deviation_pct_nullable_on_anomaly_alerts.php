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
            $table->decimal('deviation_pct', 6, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('anomaly_alerts', function (Blueprint $table) {
            $table->decimal('deviation_pct', 6, 2)->nullable(false)->change();
        });
    }
};
