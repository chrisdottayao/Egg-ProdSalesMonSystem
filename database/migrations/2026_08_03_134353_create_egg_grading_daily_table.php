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
        Schema::create('egg_grading_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('category');
            $table->integer('cases')->default(0);
            $table->integer('trays')->default(0);
            $table->integer('pieces')->default(0);
            $table->integer('total_pcs')->default(0);
            $table->timestamps();

            $table->unique(['date', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('egg_grading_daily');
    }
};
