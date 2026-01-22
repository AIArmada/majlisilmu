<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')->nullable()->index();
            $table->foreignUuid('institution_id')->nullable()->index();
            $table->foreignUuid('submitter_id')->nullable()->index();
            $table->foreignUuid('speaker_id')->nullable()->index();
            $table->foreignUuid('venue_id')->nullable()->index();
            $table->foreignUuid('series_id')->nullable()->index();

            $table->string('title')->index();
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->string('timezone')->default('Asia/Kuala_Lumpur');

            $table->string('timing_mode')->default('absolute')->index();
            $table->string('prayer_reference')->nullable()->index();
            $table->string('prayer_offset')->nullable();
            $table->string('prayer_display_text')->nullable();
            $table->decimal('prayer_calc_lat', 10, 8)->nullable();
            $table->decimal('prayer_calc_lng', 11, 8)->nullable();

            $table->string('language')->nullable()->index();
            $table->string('genre')->nullable()->index();
            $table->string('audience')->nullable()->index();

            $table->string('visibility')->default('public')->index();

            $table->string('status')->nullable()->index();

            $table->string('live_url')->nullable();
            $table->string('recording_url')->nullable();

            $table->boolean('registration_required')->default(false)->index();
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();

            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('saves_count')->default(0);
            $table->unsignedInteger('registrations_count')->default(0);
            $table->unsignedInteger('interests_count')->default(0);

            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('escalated_at')->nullable();
            $table->boolean('is_priority')->nullable();
            $table->timestamps();

            $table->index(['status', 'visibility', 'starts_at']);
            $table->index(['venue_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
