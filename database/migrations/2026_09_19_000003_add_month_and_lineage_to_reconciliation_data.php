<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_facts', function (Blueprint $table) {
            $table->unsignedTinyInteger('month')->nullable()->after('period');
            $table->json('lineage')->nullable()->after('dimensions');
            $table->index(['accounting_year_id', 'month', 'skpd_id']);
        });

        Schema::table('reconciliation_runs', function (Blueprint $table) {
            $table->unsignedTinyInteger('month')->nullable()->after('accounting_year_id');
            $table->index(['accounting_year_id', 'month']);
        });

        Schema::table('reconciliation_results', function (Blueprint $table) {
            $table->unsignedTinyInteger('month')->nullable()->after('skpd_id');
            $table->index(['reconciliation_run_id', 'month', 'skpd_id']);
        });

        Schema::table('reconciliations', function (Blueprint $table) {
            $table->unsignedTinyInteger('month')->nullable()->after('skpd_id');
            $table->unique(['accounting_year_id', 'month', 'skpd_id'], 'reconciliations_year_month_skpd_unique');
        });
    }

    public function down(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->dropUnique('reconciliations_year_month_skpd_unique');
            $table->dropColumn('month');
        });

        Schema::table('reconciliation_results', function (Blueprint $table) {
            $table->dropIndex(['reconciliation_run_id', 'month', 'skpd_id']);
            $table->dropColumn('month');
        });

        Schema::table('reconciliation_runs', function (Blueprint $table) {
            $table->dropIndex(['accounting_year_id', 'month']);
            $table->dropColumn('month');
        });

        Schema::table('financial_facts', function (Blueprint $table) {
            $table->dropIndex(['accounting_year_id', 'month', 'skpd_id']);
            $table->dropColumn(['month', 'lineage']);
        });
    }
};
