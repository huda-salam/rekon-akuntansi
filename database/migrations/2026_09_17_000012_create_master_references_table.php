<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('master_references', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100);
            $table->string('description');
            $table->string('type', 50);
            $table->decimal('level', 5, 2)->nullable();
            $table->string('parent_code', 100)->nullable();
            $table->unsignedSmallInteger('source_year')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['type', 'code']);
            $table->index(['type', 'parent_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_references');
    }
};
