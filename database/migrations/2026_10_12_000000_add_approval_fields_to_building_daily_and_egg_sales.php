<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 item 1 (S11, S5) — record approval. Additive only: existing rows
 * default to 'approved' so every total, rollup and report behaves exactly
 * as before. approved_by/approved_at stay null on old rows (shown as "—").
 *
 * Note: the table is building_daily (singular), not building_dailies.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['building_daily', 'egg_sales'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('approval_status', 20)->default('approved')->index();
                $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('approved_at')->nullable();
                $t->text('review_note')->nullable();
            });
            DB::table($table)->update(['approval_status' => 'approved']);
        }

        // building_daily already records who entered a row (user_id);
        // egg_sales had no such column.
        Schema::table('egg_sales', function (Blueprint $t) {
            $t->foreignId('entered_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('egg_sales', function (Blueprint $t) {
            $t->dropConstrainedForeignId('entered_by');
        });
        foreach (['building_daily', 'egg_sales'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('approved_by');
                $t->dropColumn(['approval_status', 'approved_at', 'review_note']);
            });
        }
    }
};
