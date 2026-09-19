<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculation_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_year_id')->nullable()->constrained('accounting_years')->nullOnDelete();
            $table->string('code', 100);
            $table->string('name');
            $table->string('version', 30)->default('1');
            $table->string('status', 30)->default('active');
            $table->text('expression');
            $table->json('input_metrics')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['accounting_year_id', 'code', 'version']);
            $table->index(['code', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculation_definitions');
    }
};
