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
        Schema::create('forecast_evaluations', function (Blueprint $table) {
            $table->id();
            $table->integer('trained_on');
            $table->decimal('mape', 8, 4);
            $table->integer('forecast_7day_total');
            $table->decimal('forecast_30day_total', 12, 2);
            $table->timestamp('evaluated_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forecast_evaluations');
    }
};
