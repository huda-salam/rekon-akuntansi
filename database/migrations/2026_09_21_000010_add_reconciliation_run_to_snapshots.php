<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reconciliation_snapshots', function (Blueprint $table) {
            $table->foreignId('reconciliation_run_id')
                ->nullable()
                ->after('reconciliation_id')
                ->constrained('reconciliation_runs')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reconciliation_snapshots', function (Blueprint $table) {
            $table->dropForeign(['reconciliation_run_id']);
            $table->dropColumn('reconciliation_run_id');
        });
    }
};
