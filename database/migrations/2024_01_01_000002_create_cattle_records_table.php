<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cattle_records', function (Blueprint $table) {
            $table->id();
            $table->string('ear_tag')->unique(); // e.g. CT-001
            $table->enum('status', ['Active', 'Sold', 'Deceased'])->default('Active');
            $table->date('entry_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cattle_records');
    }
};
