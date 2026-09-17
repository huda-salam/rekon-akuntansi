<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasTable('skpds')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('skpd_id')->references('id')->on('skpds')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['skpd_id']);
            });
        }
    }
};
