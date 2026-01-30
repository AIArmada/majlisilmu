<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speaker_members', function (Blueprint $table) {
            $table->foreignUuid('speaker_id')->index();
            $table->foreignUuid('user_id')->index();
            $table->string('role')->default('member')->index();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->primary(['speaker_id', 'user_id']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speaker_members');
    }
};
