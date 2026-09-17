<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('skpds', function (Blueprint $table) {
            $table->string('parent_code', 50)->nullable()->after('name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('skpds', function (Blueprint $table) {
            $table->dropIndex(['parent_code']);
            $table->dropColumn('parent_code');
        });
    }
};
