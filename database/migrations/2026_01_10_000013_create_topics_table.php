<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable()->index();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_official')->default(false)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // Unique name within same parent (allows same name in different branches)
            $table->unique(['parent_id', 'name']);
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('topics')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
