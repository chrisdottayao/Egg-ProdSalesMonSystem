<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });

        // Every existing Google-origin account currently holds a random,
        // unusable Hash::make(Str::random(24)) password nobody knows (that's
        // how SocialiteController used to create them) — safe to null out
        // now that "password is null" becomes the real signal for "manual
        // login isn't set up for this account yet". Accounts with a real,
        // admin-set or self-chosen password are untouched (google_id is null
        // for those, or an admin will have explicitly set one going forward).
        DB::table('users')->whereNotNull('google_id')->update(['password' => null]);
    }

    public function down(): void
    {
        // Reversing the nullable->not-null change would fail on any row that
        // is now null, so give those a fresh random password first, matching
        // the pre-migration state's spirit (unusable, but non-null).
        DB::table('users')->whereNull('password')->update([
            'password' => bcrypt(\Illuminate\Support\Str::random(24)),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
