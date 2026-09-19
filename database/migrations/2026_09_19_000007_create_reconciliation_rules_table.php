<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_year_id')->nullable()->constrained('accounting_years')->nullOnDelete();
            $table->foreignId('calculation_definition_id')->nullable()->constrained('calculation_definitions')->nullOnDelete();
            $table->string('code', 100);
            $table->string('name');
            $table->string('category', 50);
            $table->string('scope', 50)->default('document');
            $table->string('version', 30)->default('1');
            $table->string('status', 30)->default('active');
            $table->text('expression');
            $table->decimal('tolerance', 24, 2)->default(0);
            $table->json('input_metrics')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['accounting_year_id', 'code', 'version']);
            $table->index(['category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_rules');
    }
};
