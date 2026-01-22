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
        Schema::create('event_interests', function (Blueprint $table) {
            $table->uuid('event_id');
            $table->uuid('user_id');
            $table->timestamps();

            $table->primary(['event_id', 'user_id']);

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_interests');
    }
};
