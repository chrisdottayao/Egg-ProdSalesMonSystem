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
        Schema::table('egg_productions', function (Blueprint $table) {
            $table->decimal('feed_bags', 6, 2)->nullable()->after('spoilage_reason');
            $table->decimal('feed_kg_per_bag', 5, 2)->default(50)->nullable()->after('feed_bags');
            $table->decimal('feed_cost_per_bag', 8, 2)->nullable()->after('feed_kg_per_bag');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('egg_productions', function (Blueprint $table) {
            $table->dropColumn(['feed_bags', 'feed_kg_per_bag', 'feed_cost_per_bag']);
        });
    }
};
