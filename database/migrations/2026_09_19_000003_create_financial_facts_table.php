<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('financial_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('accounting_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('skpd_id')->nullable()->constrained()->nullOnDelete();
            $table->date('fact_date')->nullable()->index();
            $table->string('period', 30)->nullable()->index();
            $table->string('source_type', 80)->index();
            $table->string('transaction_type', 100)->nullable()->index();
            $table->string('document_number', 150)->nullable();
            $table->string('account_code', 100)->nullable()->index();
            $table->string('metric', 100)->index();
            $table->decimal('value', 24, 2)->nullable();
            $table->string('unit', 50)->nullable();
            $table->json('dimensions')->nullable();
            $table->timestamps();

            $table->index(['accounting_year_id', 'skpd_id', 'source_type', 'metric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_facts');
    }
};