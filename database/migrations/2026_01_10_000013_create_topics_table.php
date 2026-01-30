<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('parent_id')->nullable()->index();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('verified')->index();
            $table->boolean('is_official')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('order_column')->default(0);
            $table->timestamps();

            // Unique name within same parent (allows same name in different branches)
            $table->unique(['parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
