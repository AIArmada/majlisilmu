<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_speakers', function (Blueprint $table) {
            $table->uuid('event_id');
            $table->uuid('speaker_id');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->primary(['event_id', 'speaker_id']);
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('speaker_id')->references('id')->on('speakers')->cascadeOnDelete();
            $table->index(['speaker_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_speakers');
    }
};
