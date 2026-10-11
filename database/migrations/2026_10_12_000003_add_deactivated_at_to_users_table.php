<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 item 6 (S6): an account can be deactivated (signed out and kept
 * out) without deleting it, so its records keep their "entered by". Additive
 * only; every existing account stays active (null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('deactivated_at')->nullable();
            $t->foreignId('deactivated_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('deactivated_by');
            $t->dropColumn('deactivated_at');
        });
    }
};
