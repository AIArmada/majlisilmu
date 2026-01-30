<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_id')->nullable()->index();
            $table->foreignUuid('speaker_id')->nullable()->index();

            $table->string('title')->index();
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->string('visibility')->default('public')->index();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
