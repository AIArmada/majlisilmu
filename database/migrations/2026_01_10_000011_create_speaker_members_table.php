<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speaker_members', function (Blueprint $table) {
            $table->foreignUuid('speaker_id');
            $table->foreignUuid('user_id');
            $table->timestamps();

            $table->primary(['speaker_id', 'user_id']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speaker_members');
    }
};
