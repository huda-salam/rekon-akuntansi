<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('source_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('source_row')->nullable();
            $table->string('sheet_name', 150)->nullable();
            $table->string('record_type', 80)->nullable()->index();
            $table->json('payload');
            $table->timestamps();

            $table->index(['source_document_id', 'sheet_name', 'source_row']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_records');
    }
};