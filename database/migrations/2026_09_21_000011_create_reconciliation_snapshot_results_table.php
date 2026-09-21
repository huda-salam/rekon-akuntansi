<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_snapshot_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('reconciliation_snapshots')->restrictOnDelete();
            $table->unsignedBigInteger('source_result_id')->nullable();
            $table->string('rule_code', 100)->nullable();
            $table->string('rule_name')->nullable();
            $table->string('category', 40)->nullable();
            $table->unsignedBigInteger('skpd_id')->nullable();
            $table->unsignedTinyInteger('month')->nullable();
            $table->string('period', 50)->nullable();
            $table->string('status', 30);
            $table->decimal('expected_value', 20, 2)->nullable();
            $table->decimal('actual_value', 20, 2)->nullable();
            $table->decimal('variance', 20, 2)->nullable();
            $table->json('inputs')->nullable();
            $table->json('lineage')->nullable();
            $table->text('explanation')->nullable();
            $table->json('review')->nullable();
            $table->timestamps();

            $table->index(['snapshot_id', 'status']);
            $table->index(['snapshot_id', 'rule_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_snapshot_results');
    }
};
