<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hen_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->unique(); // e.g. HB-2024-001
            $table->integer('batch_size');
            $table->enum('status', ['Active', 'Culled', 'Mortality'])->default('Active');
            $table->date('entry_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hen_batches');
    }
};
