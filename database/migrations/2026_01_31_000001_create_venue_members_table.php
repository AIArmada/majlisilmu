<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('venue_id')->index();
            $table->foreignUuid('user_id')->index();
            $table->string('role')->default('member')->index();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['venue_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_members');
    }
};
