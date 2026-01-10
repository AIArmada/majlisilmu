<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_topics', function (Blueprint $table) {
            $table->uuid('event_id');
            $table->uuid('topic_id');
            $table->timestamps();

            $table->primary(['event_id', 'topic_id']);
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('topic_id')->references('id')->on('topics')->cascadeOnDelete();
            $table->index(['topic_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_topics');
    }
};
