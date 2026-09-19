<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_run_id')->constrained('reconciliation_runs')->cascadeOnDelete();
            $table->foreignId('reconciliation_rule_id')->constrained('reconciliation_rules')->restrictOnDelete();
            $table->foreignId('source_document_id')->nullable()->constrained('source_documents')->nullOnDelete();
            $table->foreignId('skpd_id')->nullable()->constrained('skpds')->nullOnDelete();
            $table->string('period', 30)->nullable();
            $table->string('status', 30);
            $table->decimal('expected_value', 24, 2)->nullable();
            $table->decimal('actual_value', 24, 2)->nullable();
            $table->decimal('variance', 24, 2)->nullable();
            $table->json('inputs')->nullable();
            $table->json('lineage')->nullable();
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->index(['reconciliation_run_id', 'status']);
            $table->index(['reconciliation_rule_id', 'skpd_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_results');
    }
};
