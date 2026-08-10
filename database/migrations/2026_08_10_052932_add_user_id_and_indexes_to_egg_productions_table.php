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
            // Nullable: historical bulk-imported rows have no real contributor —
            // left null rather than attributed to whoever happened to run the import.
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();

            $table->index('date');
            $table->index('egg_size');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('egg_productions', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->dropIndex(['egg_size']);
            $table->dropIndex(['user_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
