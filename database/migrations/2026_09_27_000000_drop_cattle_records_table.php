<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cattle tracking is out of scope for this system — SPC Farm Magalang
     * manages cattle through a separate system. This table was never wired
     * into any controller, route, or view, so it is dropped here rather
     * than left as dead schema.
     */
    public function up(): void
    {
        Schema::dropIfExists('cattle_records');
    }

    public function down(): void
    {
        Schema::create('cattle_records', function (Blueprint $table) {
            $table->id();
            $table->string('ear_tag')->unique();
            $table->enum('status', ['Active', 'Sold', 'Deceased'])->default('Active');
            $table->date('entry_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};
