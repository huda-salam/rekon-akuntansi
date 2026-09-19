<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reconciliation_snapshots', function (Blueprint $table) {
            $table->unsignedTinyInteger('month')->nullable()->after('skpd_name');
            $table->string('reconciliation_type', 30)->default('REGULAR')->after('month');
            $table->unsignedInteger('sequence')->nullable()->after('reconciliation_type');
            $table->index(['accounting_year', 'month', 'skpd_code']);
        });
    }

    public function down(): void
    {
        Schema::table('reconciliation_snapshots', function (Blueprint $table) {
            $table->dropIndex(['accounting_year', 'month', 'skpd_code']);
            $table->dropColumn(['month', 'reconciliation_type', 'sequence']);
        });
    }
};
