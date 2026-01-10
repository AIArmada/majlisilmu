<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('institution_id')->nullable()->index();
            $table->uuid('venue_id')->nullable()->index();

            $table->string('title')->index();
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->enum('visibility', ['public', 'unlisted', 'private'])->default('public')->index();

            $table->string('default_language')->nullable()->index();
            $table->string('default_audience')->nullable()->index();

            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->nullOnDelete();
            $table->foreign('venue_id')->references('id')->on('venues')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
