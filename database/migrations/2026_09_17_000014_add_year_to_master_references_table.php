<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('master_references', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->nullable()->after('id')->index();
        });

        Schema::table('master_references', function (Blueprint $table) {
            $table->dropUnique(['type', 'code']);
            $table->unique(['year', 'type', 'code']);
            $table->dropColumn('source_year');
        });
    }

    public function down(): void
    {
        Schema::table('master_references', function (Blueprint $table) {
            $table->unsignedSmallInteger('source_year')->nullable()->after('parent_code');
            $table->dropUnique(['year', 'type', 'code']);
            $table->unique(['type', 'code']);
            $table->dropColumn('year');
        });
    }
};
