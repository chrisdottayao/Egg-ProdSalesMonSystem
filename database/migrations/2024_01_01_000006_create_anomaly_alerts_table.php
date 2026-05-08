<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anomaly_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type');                          // e.g. Production Drop, Mortality Spike
            $table->enum('severity', ['high', 'medium', 'low'])->default('medium');
            $table->date('alert_date');                      // date the anomaly occurred
            $table->string('expected_value');                // e.g. "450 eggs"
            $table->string('actual_value');                  // e.g. "290 eggs"
            $table->decimal('deviation_pct', 6, 2);         // e.g. -35.6
            $table->text('description');
            $table->enum('status', ['unreviewed', 'reviewed', 'resolved'])->default('unreviewed');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'alert_date']);          // one alert per type per day
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomaly_alerts');
    }
};
