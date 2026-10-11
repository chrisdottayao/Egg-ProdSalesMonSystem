<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 item 2 (S7): the Audit Log shows where each action came from.
 * Additive only; existing rows keep null (shown as "—").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->string('ip_address', 45)->nullable()->after('details');
            $t->string('user_agent', 512)->nullable()->after('ip_address');
            $t->index('created_at');
            $t->index('action');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->dropIndex(['created_at']);
            $t->dropIndex(['action']);
            $t->dropColumn(['ip_address', 'user_agent']);
        });
    }
};
