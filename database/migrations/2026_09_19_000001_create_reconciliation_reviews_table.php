<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_result_id')->constrained('reconciliation_results')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 30);
            $table->text('note')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['reconciliation_result_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_reviews');
    }
};
