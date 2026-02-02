<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_topic', function (Blueprint $table) {
            $table->uuid('event_id');
            $table->uuid('topic_id');
            $table->unsignedSmallInteger('order_column')->default(0);
            $table->timestamps();

            $table->primary(['event_id', 'topic_id']);

            $table->index(['topic_id', 'event_id']);
            $table->index(['event_id', 'order_column']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_topic');
    }
};
