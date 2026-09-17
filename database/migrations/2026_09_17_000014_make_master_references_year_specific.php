<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('master_references', function (Blueprint $table) {
            $table->dropUnique(['type', 'code']);
        });

        Schema::table('master_references', function (Blueprint $table) {
            $table->renameColumn('source_year', 'year');
        });

        Schema::table('master_references', function (Blueprint $table) {
            $table->unique(['year', 'type', 'code']);
            $table->index(['year', 'type', 'parent_code']);
        });
    }

    public function down(): void
    {
        Schema::table('master_references', function (Blueprint $table) {
            $table->dropUnique(['year', 'type', 'code']);
            $table->dropIndex(['year', 'type', 'parent_code']);
        });

        Schema::table('master_references', function (Blueprint $table) {
            $table->renameColumn('year', 'source_year');
            $table->unique(['type', 'code']);
        });
    }
};
