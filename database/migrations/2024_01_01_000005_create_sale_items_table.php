<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cull_records', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('hen_batch_id')->nullable()->constrained('hen_batches')->nullOnDelete();
            $table->integer('quantity_culled');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cull_records');
    }
};
