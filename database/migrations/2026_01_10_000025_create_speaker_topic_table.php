<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('speaker_topic')) {
            return;
        }

        Schema::create('speaker_topic', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('speaker_id')->index();
            $table->foreignUuid('topic_id')->index();
            $table->unique(['speaker_id', 'topic_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speaker_topic');
    }
};
