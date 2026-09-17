<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_year_id')->constrained('accounting_years')->restrictOnDelete();
            $table->foreignId('skpd_id')->constrained('skpds')->restrictOnDelete();
            $table->string('authorization_number', 100)->nullable()->index();
            $table->date('authorization_date')->nullable()->index();
            $table->string('type', 30)->index();
            $table->string('description')->nullable();
            $table->decimal('total_amount', 20, 2)->default(0);
            $table->json('source_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('authorization_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('authorization_id')->constrained('authorizations')->cascadeOnDelete();
            $table->string('account_code', 100)->nullable()->index();
            $table->string('account_name')->nullable();
            $table->string('description')->nullable();
            $table->decimal('quantity', 20, 4)->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('amount', 20, 2)->default(0);
            $table->string('source_reference', 150)->nullable()->index();
            $table->json('source_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_details');
        Schema::dropIfExists('authorizations');
    }
};
