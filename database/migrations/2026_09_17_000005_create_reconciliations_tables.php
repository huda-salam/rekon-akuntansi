<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_year_id')->constrained('accounting_years')->restrictOnDelete();
            $table->foreignId('skpd_id')->constrained('skpds')->restrictOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reconciliation_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained('reconciliations')->cascadeOnDelete();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('match_status', 30)->default('unmatched')->index();
            $table->decimal('source_amount', 20, 2)->default(0);
            $table->decimal('matched_amount', 20, 2)->default(0);
            $table->decimal('difference_amount', 20, 2)->default(0);
            $table->text('notes')->nullable();
            $table->json('match_payload')->nullable();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_details');
        Schema::dropIfExists('reconciliations');
    }
};
