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
        Schema::table('flock_alerts', function (Blueprint $table) {
            $table->decimal('expected_rate', 5, 2)->nullable()->after('recommendation');
            $table->decimal('deviation', 6, 2)->nullable()->after('expected_rate');
            $table->string('cluster_id')->nullable()->after('deviation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flock_alerts', function (Blueprint $table) {
            $table->dropColumn(['expected_rate', 'deviation', 'cluster_id']);
        });
    }
};
