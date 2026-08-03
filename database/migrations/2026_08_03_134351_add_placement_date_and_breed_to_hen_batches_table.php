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
            $table->date('placement_date')->nullable()->after('entry_date');
            $table->string('breed')->nullable()->after('placement_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hen_batches', function (Blueprint $table) {
            $table->dropColumn(['placement_date', 'breed']);
        });
    }
};
