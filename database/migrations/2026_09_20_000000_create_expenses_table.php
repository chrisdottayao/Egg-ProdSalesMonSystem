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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            // "Building" in this system is a HenBatch (see building_daily.hen_batch_id) —
            // there is no standalone buildings table. Null = farm-wide, allocated
            // to buildings by population share (see ExpenseAllocator).
            $table->foreignId('building_id')->nullable()->constrained('hen_batches')->nullOnDelete();
            $table->date('date')->index();
            $table->string('category', 20); // feed, vaccine, vitamins, medicine, restocking, electricity, manpower, other
            $table->string('description');
            $table->decimal('quantity', 10, 2)->nullable();
            $table->string('unit', 15)->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('kg_total', 10, 2)->nullable(); // feed only, per IT expert point 1
            $table->string('supplier')->nullable();
            $table->boolean('is_estimated')->default(false);
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence', 20)->nullable(); // e.g. "weekly-Mon"
            $table->string('receipt_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
