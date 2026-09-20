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
        // SocialiteController looks up by google_id on every Google login;
        // it had no index at all, forcing a full table scan (and previously
        // also defeated the email index via an orWhere mixing the two).
        Schema::table('users', function (Blueprint $table) {
            $table->index('google_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['google_id']);
        });
    }
};
