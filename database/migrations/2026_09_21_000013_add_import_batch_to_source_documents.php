<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('source_documents', function (Blueprint $table) {
            $table->foreignId('import_batch_id')
                ->nullable()
                ->after('accounting_year_id')
                ->constrained('import_batches')
                ->nullOnDelete();

            $table->index(['import_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('source_documents', function (Blueprint $table) {
            $table->dropForeign(['import_batch_id']);
            $table->dropIndex(['import_batch_id', 'status']);
            $table->dropColumn('import_batch_id');
        });
    }
};
