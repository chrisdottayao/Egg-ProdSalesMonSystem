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
        Schema::create('building_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('hen_batch_id')->constrained('hen_batches')->restrictOnDelete();
            $table->integer('population');
            $table->integer('mortality')->default(0);
            $table->integer('net_birds');
            $table->integer('eggs_house');
            $table->integer('eggs_eggroom');
            $table->integer('soft_shell')->default(0);
            $table->integer('age_weeks')->nullable();
            $table->decimal('feed_bags', 6, 2)->nullable();
            $table->decimal('prod_rate', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['date', 'hen_batch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('building_daily');
    }
};
