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
        Schema::create('weather_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('temp_max', 5, 2)->nullable();
            $table->decimal('temp_min', 5, 2)->nullable();
            $table->decimal('temp_mean', 5, 2)->nullable();
            $table->decimal('humidity_mean', 5, 2)->nullable();
            $table->decimal('precipitation', 6, 2)->nullable();
            $table->decimal('thi', 5, 2)->nullable();
            $table->string('source', 20); // 'archive' or 'forecast'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather_daily');
    }
};
