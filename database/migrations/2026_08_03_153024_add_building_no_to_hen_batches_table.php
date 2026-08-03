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
        Schema::table('hen_batches', function (Blueprint $table) {
            // Not unique: a building holds one flock at a time, but a batch's
            // *history* (culled predecessors, future replacements) can share
            // the same building_no over the farm's lifetime. "One active flock
            // per building" is enforced at the application layer (import),
            // not by a DB constraint.
            $table->unsignedTinyInteger('building_no')->nullable()->after('building');
            $table->index('building_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hen_batches', function (Blueprint $table) {
            $table->dropIndex(['building_no']);
            $table->dropColumn('building_no');
        });
    }
};
