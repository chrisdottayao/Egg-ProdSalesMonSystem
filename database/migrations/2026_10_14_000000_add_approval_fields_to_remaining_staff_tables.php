<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3B item 1 (S11) — the same approval columns as
 * 2026_10_12_000000, on every other table staff write to. Additive only:
 * existing rows default to 'approved', so totals behave exactly as before.
 *
 * egg_productions already records who entered a row (user_id); the other
 * three had no user column, so they get entered_by.
 */
return new class extends Migration
{
    private const TABLES = ['egg_grading_daily', 'expenses', 'cull_records', 'egg_productions'];

    private const NEEDS_ENTERED_BY = ['egg_grading_daily', 'expenses', 'cull_records'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->string('approval_status', 20)->default('approved')->index();
                $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('approved_at')->nullable();
                $t->text('review_note')->nullable();
                if (in_array($table, self::NEEDS_ENTERED_BY, true)) {
                    $t->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
                }
            });
            DB::table($table)->update(['approval_status' => 'approved']);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (in_array($table, self::NEEDS_ENTERED_BY, true)) {
                    $t->dropConstrainedForeignId('entered_by');
                }
                $t->dropConstrainedForeignId('approved_by');
                $t->dropColumn(['approval_status', 'approved_at', 'review_note']);
            });
        }
    }
};
