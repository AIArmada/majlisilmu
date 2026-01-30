<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')->nullable()->index();
            $table->foreignUuid('institution_id')->nullable();
            $table->foreignUuid('submitter_id')->nullable()->index();
            $table->foreignUuid('venue_id')->nullable();
            $table->foreignUuid('space_id')->nullable()->index();
            $table->nullableUuidMorphs('organizer');
            $table->foreignUuid('event_type_id')->nullable();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('timezone')->default('Asia/Kuala_Lumpur');

            $table->string('timing_mode')->default('absolute');
            $table->string('prayer_reference')->nullable();
            $table->string('prayer_offset')->nullable();
            $table->string('prayer_display_text')->nullable();

            $table->string('gender')->default('all');
            $table->jsonb('age_group')->nullable();
            $table->boolean('children_allowed')->default(true);

            $table->string('event_format')->default('physical');

            $table->string('visibility')->default('public');

            $table->string('status')->nullable();

            $table->string('live_url')->nullable();
            $table->string('event_url')->nullable();
            $table->string('recording_url')->nullable();

            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('saves_count')->default(0);
            $table->unsignedInteger('registrations_count')->default(0);
            $table->unsignedInteger('interests_count')->default(0);
            $table->unsignedInteger('going_count')->default(0);

            $table->timestamp('published_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->boolean('is_priority')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            // Optimized composite indexes for common query patterns
            // Main listing query: WHERE status='approved' AND visibility='public' AND starts_at >= NOW()
            $table->index(['status', 'visibility', 'starts_at'], 'events_status_visibility_starts_at');
            
            // Filter queries with starts_at sorting
            $table->index(['event_type_id', 'starts_at'], 'events_event_type_starts_at');
            $table->index(['institution_id', 'starts_at'], 'events_institution_starts_at');
            $table->index(['venue_id', 'starts_at'], 'events_venue_starts_at');
            $table->index(['gender', 'starts_at'], 'events_gender_starts_at');
            
            // Event format filtering (physical/online/hybrid)
            $table->index(['status', 'visibility', 'event_format', 'starts_at'], 'events_format_filter');
            $table->index(['event_format', 'starts_at'], 'events_format_upcoming');
            
            // Sitemap generation: WHERE status='approved' AND visibility='public' ORDER BY updated_at DESC
            $table->index(['status', 'visibility', 'updated_at'], 'events_sitemap');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
