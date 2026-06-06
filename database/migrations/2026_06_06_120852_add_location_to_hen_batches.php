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
            $table->string('pen_number')->nullable()->after('notes');
            $table->string('building')->nullable()->after('pen_number');
        });
    }

    public function down(): void
    {
        Schema::table('hen_batches', function (Blueprint $table) {
            $table->dropColumn(['pen_number', 'building']);
        });
    }
};
