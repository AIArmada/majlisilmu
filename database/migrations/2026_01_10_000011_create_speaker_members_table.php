<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speaker_members', function (Blueprint $table) {
            $table->uuid('speaker_id');
            $table->uuid('user_id');
            $table->enum('role', ['owner', 'admin', 'editor'])->default('editor')->index();
            $table->timestamps();

            $table->primary(['speaker_id', 'user_id']);
            $table->foreign('speaker_id')->references('id')->on('speakers')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speaker_members');
    }
};
