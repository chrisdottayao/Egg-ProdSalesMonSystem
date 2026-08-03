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
        Schema::table('cull_records', function (Blueprint $table) {
            $table->integer('heads_sold')->nullable()->after('quantity_culled');
            $table->decimal('price_per_head', 8, 2)->nullable()->after('heads_sold');
            $table->string('buyer')->nullable()->after('price_per_head');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cull_records', function (Blueprint $table) {
            $table->dropColumn(['heads_sold', 'price_per_head', 'buyer']);
        });
    }
};
