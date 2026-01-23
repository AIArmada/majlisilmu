<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('references', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('type')->default('kitab')->index(); // e.g. book, kitab, article, video, etc.
            $table->string('publication_year')->nullable();
            $table->string('publisher')->nullable();
            $table->text('description')->nullable();
            $table->string('external_link')->nullable(); // Link to buy or view
            $table->boolean('is_canonical')->default(false)->index(); // Generic/Official reference
            $table->timestamps();
        });

        Schema::create('reference_topic', function (Blueprint $table) {
            $table->foreignUuid('reference_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('topic_id')->constrained()->cascadeOnDelete();
            $table->primary(['reference_id', 'topic_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reference_topic');
        Schema::dropIfExists('references');
    }
};
