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
        Schema::create('flock_alerts', function (Blueprint $table) {
            $table->id();
            // null hen_batch_id = farm-wide condition (revenue, culling rate, count mismatch, etc.)
            $table->foreignId('hen_batch_id')->nullable()->constrained('hen_batches')->nullOnDelete();
            $table->string('condition');
            $table->enum('severity', ['warning', 'critical'])->default('warning');
            $table->text('recommendation');
            $table->date('triggered_since');
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->unsignedTinyInteger('normal_streak')->default(0); // consecutive in-range days, for auto-resolve
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['hen_batch_id', 'condition', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flock_alerts');
    }
};
