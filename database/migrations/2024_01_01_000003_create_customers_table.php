<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egg_productions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->integer('eggs_collected');
            $table->integer('active_hens');
            $table->string('egg_size')->default('Large'); // Peewee/Small/Medium/Large/XL/Jumbo
            $table->decimal('egg_weight', 6, 2)->nullable(); // avg grams
            $table->integer('mortality')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_productions');
    }
};
