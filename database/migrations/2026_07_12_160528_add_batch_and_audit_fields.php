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
        // Add spoilage to egg_productions (representing batch collection & details)
        Schema::table('egg_productions', function (Blueprint $table) {
            $table->integer('spoilage_count')->default(0)->after('mortality');
            $table->text('spoilage_reason')->nullable()->after('spoilage_count');
        });

        // Link egg_sales to a specific egg_productions batch
        Schema::table('egg_sales', function (Blueprint $table) {
            $table->foreignId('production_id')->nullable()->after('id')->constrained('egg_productions')->nullOnDelete();
        });

        // Create persistent audit logs table
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // create, update, delete
            $table->string('model_type');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('details'); // JSON details
            $table->boolean('inconsistency_flagged')->default(false);
            $table->string('inconsistency_rule')->nullable();
            $table->boolean('resolved')->default(false);
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');

        Schema::table('egg_sales', function (Blueprint $table) {
            $table->dropForeign(['production_id']);
            $table->dropColumn('production_id');
        });

        Schema::table('egg_productions', function (Blueprint $table) {
            $table->dropColumn(['spoilage_count', 'spoilage_reason']);
        });
    }
};

