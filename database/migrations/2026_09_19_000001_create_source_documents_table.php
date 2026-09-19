<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('source_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename');
            $table->string('document_type', 80)->nullable()->index();
            $table->string('source_category', 80)->nullable()->index();
            $table->string('mime_type', 150)->nullable();
            $table->string('checksum_sha256', 64)->unique();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->string('status', 30)->default('IMPORTED')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_documents');
    }
};