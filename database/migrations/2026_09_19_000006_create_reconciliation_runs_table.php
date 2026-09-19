<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_year_id')->constrained('accounting_years')->restrictOnDelete();
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('running');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('source_document_ids')->nullable();
            $table->json('parameters')->nullable();
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['accounting_year_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
    }
};
