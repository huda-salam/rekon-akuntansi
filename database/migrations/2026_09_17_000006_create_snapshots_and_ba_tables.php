<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reconciliation_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->unique()->constrained('reconciliations')->restrictOnDelete();
            $table->unsignedSmallInteger('accounting_year');
            $table->string('skpd_code', 50);
            $table->string('skpd_name');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('finalized_at');
            $table->string('snapshot_hash', 64)->unique();
            $table->json('snapshot_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('reconciliation_snapshot_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('reconciliation_snapshots')->restrictOnDelete();
            $table->string('source_type', 40);
            $table->string('source_reference', 150)->nullable();
            $table->string('match_status', 30)->default('unmatched');
            $table->decimal('source_amount', 20, 2)->default(0);
            $table->decimal('matched_amount', 20, 2)->default(0);
            $table->decimal('difference_amount', 20, 2)->default(0);
            $table->text('notes')->nullable();
            $table->json('detail_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('berita_acaras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->unique()->constrained('reconciliations')->restrictOnDelete();
            $table->foreignId('snapshot_id')->unique()->constrained('reconciliation_snapshots')->restrictOnDelete();
            $table->string('number', 150)->unique();
            $table->date('date');
            $table->string('signatory_official_name');
            $table->string('signatory_official_nip', 30)->nullable();
            $table->string('signatory_official_position');
            $table->string('document_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('berita_acaras');
        Schema::dropIfExists('reconciliation_snapshot_details');
        Schema::dropIfExists('reconciliation_snapshots');
    }
};
