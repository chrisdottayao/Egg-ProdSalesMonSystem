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
        // Prototype 3Q — "this flock's lifecycle is over," independent of
        // is_tracked (a culled/depopulated building can still be one of the
        // 3 actively-tracked demo buildings — is_tracked says "shown in the
        // UI," ended_at says "no longer producing"). NULL = active flock.
        Schema::table('hen_batches', function (Blueprint $table) {
            $table->date('ended_at')->nullable()->after('is_tracked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hen_batches', function (Blueprint $table) {
            $table->dropColumn('ended_at');
        });
    }
};
