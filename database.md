# Majlis Ilmu — Laravel 12 DB Schema (UUIDv7)

This document contains a **single “all-in-one” migration** for Majlis Ilmu, using **UUID primary keys** across the domain.  
You said you’re on **Laravel 12 with UUIDv7 integrated**, so this migration **does NOT** use `orderedUuid()`.

## Assumptions
- You will generate UUIDv7 IDs at the model/application layer (Laravel 12 UUIDv7 integration).
- DB columns are `uuid` (string/binary depending on driver). This schema works for MySQL and Postgres.

---

## How to use

1. Create a migration file, e.g.:

`database/migrations/2026_01_10_000001_create_majlis_schema_uuidv7.php`

2. Copy the PHP migration code below into that file.
3. Run:
```bash
php artisan migrate
```

> If your project already has Laravel’s default `users` table migrated, remove the `users` block (or split this into multiple migrations).

---

## Model ID generation (quick note)

If you’re using Laravel’s UUIDv7 integration, ensure your models are configured for UUID primary keys:

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Event extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
}
```

(If your UUIDv7 integration requires overriding `newUniqueId()`, do it in a base model once.)

---

# ✅ Migration (UUID everywhere)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * USERS
         * If your Laravel app already has users, remove this block.
         */
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        /**
         * GLOBAL ROLES (simple RBAC)
         */
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique(); // super_admin, moderator, etc
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->uuid('role_id');
            $table->uuid('user_id');
            $table->timestamps();

            $table->primary(['role_id', 'user_id']);
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        /**
         * MALAYSIA GEO (state/district)
         */
        Schema::create('states', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('state_id');
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->foreign('state_id')->references('id')->on('states')->cascadeOnDelete();
            $table->unique(['state_id', 'slug']);
            $table->index(['state_id', 'name']);
        });

        /**
         * MEDIA ASSETS (QRs, posters, etc.)
         */
        Schema::create('media_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('disk')->default('public'); // s3, r2, public, etc.
            $table->string('path'); // storage path
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('original_name')->nullable();
            $table->uuid('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['disk', 'path']);
        });

        /**
         * INSTITUTIONS (Masjid/Surau)
         */
        Schema::create('institutions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('type')->default('masjid')->index(); // masjid|surau|others
            $table->string('name')->index();
            $table->string('slug')->unique();

            $table->text('description')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('website_url')->nullable();

            $table->unsignedSmallInteger('state_id')->nullable()->index();
            $table->unsignedInteger('district_id')->nullable()->index();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('postcode', 16)->nullable();
            $table->string('city')->nullable();

            // Later upgrade: PostGIS geography point
            $table->decimal('lat', 10, 7)->nullable()->index();
            $table->decimal('lng', 10, 7)->nullable()->index();

            $table->enum('verification_status', ['unverified', 'pending', 'verified', 'rejected'])
                ->default('unverified')->index();
            $table->unsignedSmallInteger('trust_score')->default(0)->index(); // 0..100

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();
            $table->foreign('district_id')->references('id')->on('districts')->nullOnDelete();
        });

        /**
         * INSTITUTION USER (scoped roles)
         */
        Schema::create('institution_user', function (Blueprint $table) {
            $table->uuid('institution_id');
            $table->uuid('user_id');
            $table->enum('role', ['owner', 'admin', 'editor'])->default('editor')->index();
            $table->timestamps();

            $table->primary(['institution_id', 'user_id']);
            $table->foreign('institution_id')->references('id')->on('institutions')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        /**
         * VENUES
         */
        Schema::create('venues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('institution_id')->nullable()->index();
            $table->string('name')->index();
            $table->string('slug')->unique();

            $table->unsignedSmallInteger('state_id')->nullable()->index();
            $table->unsignedInteger('district_id')->nullable()->index();

            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('postcode', 16)->nullable();
            $table->string('city')->nullable();

            $table->decimal('lat', 10, 7)->nullable()->index();
            $table->decimal('lng', 10, 7)->nullable()->index();

            $table->string('google_maps_place_id')->nullable()->index();
            $table->string('waze_place_url')->nullable();

            $table->json('facilities')->nullable(); // {"parking":true,"oku":true,"women_section":true}
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institution_id')->references('id')->on('institutions')->nullOnDelete();
            $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();
            $table->foreign('district_id')->references('id')->on('districts')->nullOnDelete();
        });

        /**
         * SPEAKERS
         */
        Schema::create('speakers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->index();
            $table->string('slug')->unique();
            $table->text('bio')->nullable();

            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable()->index();

            $table->string('avatar_url')->nullable();
            $table->string('website_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();

            $table->enum('verification_status', ['unverified', 'pending', 'verified', 'rejected'])
                ->default('unverified')->index();
            $table->unsignedSmallInteger('trust_score')->default(0)->index();

            $table->timestamps();
            $table->softDeletes();
        });

        /**
         * SPEAKER USER (scoped roles)
         */
        Schema::create('speaker_user', function (Blueprint $table) {
            $table->uuid('speaker_id');
            $table->uuid('user_id');
            $table->enum('role', ['owner', 'admin', 'editor'])->default('editor')->index();
            $table->timestamps();

            $table->primary(['speaker_id', 'user_id']);
            $table->foreign('speaker_id')->references('id')->on('speakers')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        /**
         * DONATION ACCOUNTS (owned by institution)
         * Events reference donation_account_id so submitters can’t inject random accounts.
         */
        Schema::create('donation_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('institution_id')->index();

            $table->string('label')->nullable(); // "Tabung Masjid"
            $table->string('recipient_name')->index();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('duitnow_id')->nullable();
            $table->uuid('qr_asset_id')->nullable()->index();

            $table->enum('verification_status', ['unverified', 'pending', 'verified', 'rejected'])
                ->default('unverified')->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institution_id')->references('id')->on('institutions')->cascadeOnDelete();
            $table->foreign('qr_asset_id')->references('id')->on('media_assets')->nullOnDelete();
            $table->index(['institution_id', 'recipient_name']);
        });

        /**
         * TOPICS / TAGS
         */
        Schema::create('topics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('category')->nullable()->index(); // aqidah, fiqh, sirah, etc.
            $table->boolean('is_official')->default(false)->index();
            $table->timestamps();
        });

        /**
         * SERIES (recurring programs)
         */
        Schema::create('series', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('institution_id')->nullable()->index();
            $table->uuid('venue_id')->nullable()->index();

            $table->string('title')->index();
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->enum('visibility', ['public', 'unlisted', 'private'])->default('public')->index();

            $table->string('default_language')->nullable()->index(); // bm/en/ar
            $table->string('default_audience')->nullable()->index(); // muslimah/youth/family/etc

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institution_id')->references('id')->on('institutions')->nullOnDelete();
            $table->foreign('venue_id')->references('id')->on('venues')->nullOnDelete();
        });

        /**
         * EVENTS / TALKS
         */
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('institution_id')->nullable()->index(); // organizer
            $table->uuid('venue_id')->nullable()->index();
            $table->uuid('series_id')->nullable()->index();

            $table->string('title')->index();
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->unsignedSmallInteger('state_id')->nullable()->index();
            $table->unsignedInteger('district_id')->nullable()->index();

            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->string('timezone')->default('Asia/Kuala_Lumpur');

            $table->string('language')->nullable()->index(); // bm/en/ar
            $table->string('genre')->nullable()->index();    // kuliah, ceramah, tazkirah, forum
            $table->string('audience')->nullable()->index(); // muslimah, youth, family

            $table->enum('visibility', ['public', 'unlisted', 'private'])->default('public')->index();

            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'cancelled', 'postponed'])
                ->default('pending')->index();

            $table->string('livestream_url')->nullable();
            $table->string('recording_url')->nullable();
            $table->uuid('donation_account_id')->nullable()->index();

            $table->boolean('registration_required')->default(false)->index();
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();

            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('saves_count')->default(0);
            $table->unsignedInteger('registrations_count')->default(0);

            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institution_id')->references('id')->on('institutions')->nullOnDelete();
            $table->foreign('venue_id')->references('id')->on('venues')->nullOnDelete();
            $table->foreign('series_id')->references('id')->on('series')->nullOnDelete();
            $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();
            $table->foreign('district_id')->references('id')->on('districts')->nullOnDelete();
            $table->foreign('donation_account_id')->references('id')->on('donation_accounts')->nullOnDelete();

            $table->index(['status', 'visibility', 'starts_at']);
            $table->index(['state_id', 'district_id', 'starts_at']);
            $table->index(['venue_id', 'starts_at']);
        });

        /**
         * EVENT SPEAKER (many-to-many)
         */
        Schema::create('event_speaker', function (Blueprint $table) {
            $table->uuid('event_id');
            $table->uuid('speaker_id');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->primary(['event_id', 'speaker_id']);
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('speaker_id')->references('id')->on('speakers')->cascadeOnDelete();
            $table->index(['speaker_id', 'event_id']);
        });

        /**
         * EVENT TOPIC (many-to-many)
         */
        Schema::create('event_topic', function (Blueprint $table) {
            $table->uuid('event_id');
            $table->uuid('topic_id');
            $table->timestamps();

            $table->primary(['event_id', 'topic_id']);
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('topic_id')->references('id')->on('topics')->cascadeOnDelete();
            $table->index(['topic_id', 'event_id']);
        });

        /**
         * EVENT MEDIA LINKS (structured)
         */
        Schema::create('event_media_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->enum('type', ['livestream', 'recording', 'playlist', 'slides', 'other'])->index();
            $table->string('provider')->nullable()->index(); // youtube, facebook, zoom
            $table->string('url');
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->index(['event_id', 'type']);
        });

        /**
         * EVENT SUBMISSIONS
         */
        Schema::create('event_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('submitted_by')->nullable()->index();

            $table->enum('source', ['institution', 'speaker', 'public', 'import'])->default('public')->index();
            $table->string('submitter_name')->nullable();
            $table->string('submitter_contact')->nullable();

            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
        });

        /**
         * MODERATION REVIEWS
         */
        Schema::create('moderation_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('reviewer_id')->nullable()->index();

            $table->enum('decision', ['approved', 'rejected', 'needs_changes'])->index();
            $table->text('note')->nullable();
            $table->string('reason_code')->nullable()->index(); // donation_changed, time_changed, etc.

            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('reviewer_id')->references('id')->on('users')->nullOnDelete();
        });

        /**
         * REPORTS (polymorphic by entity_type/entity_id)
         */
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('reporter_id')->nullable()->index();
            $table->uuid('handled_by')->nullable()->index();

            $table->string('entity_type')->index(); // event, institution, speaker, donation_account
            $table->uuid('entity_id')->index();

            $table->enum('category', [
                'wrong_info',
                'cancelled_not_updated',
                'fake_speaker',
                'inappropriate_content',
                'donation_scam',
                'other',
            ])->index();

            $table->text('description')->nullable();

            $table->enum('status', ['open', 'triaged', 'resolved', 'dismissed'])->default('open')->index();
            $table->text('resolution_note')->nullable();

            $table->timestamps();

            $table->foreign('reporter_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('handled_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['entity_type', 'entity_id']);
        });

        /**
         * SAVED SEARCHES / PREFERENCES
         */
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();

            $table->string('name');
            $table->string('query')->nullable();
            $table->json('filters')->nullable(); // evolve without migrations

            $table->unsignedSmallInteger('radius_km')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->enum('notify', ['off', 'instant', 'daily', 'weekly'])->default('daily')->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        /**
         * EVENT SAVES (bookmarks)
         */
        Schema::create('event_saves', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->uuid('event_id');
            $table->timestamps();

            $table->primary(['user_id', 'event_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->index(['event_id', 'user_id']);
        });

        /**
         * REGISTRATIONS
         */
        Schema::create('registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('user_id')->nullable()->index(); // allow guests

            $table->string('name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();

            $table->enum('status', ['registered', 'cancelled', 'attended', 'no_show'])
                ->default('registered')->index();

            $table->string('checkin_token', 64)->nullable()->unique(); // phase 2

            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->unique(['event_id', 'email']); // simple guest dedupe
        });

        /**
         * AUDIT LOGS (append-only)
         */
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_id')->nullable()->index();

            $table->string('entity_type')->index();
            $table->uuid('entity_id')->index();

            $table->string('action')->index(); // created, updated, approved, rejected
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip')->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['entity_type', 'entity_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('registrations');
        Schema::dropIfExists('event_saves');
        Schema::dropIfExists('saved_searches');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('moderation_reviews');
        Schema::dropIfExists('event_submissions');
        Schema::dropIfExists('event_media_links');
        Schema::dropIfExists('event_topic');
        Schema::dropIfExists('event_speaker');
        Schema::dropIfExists('events');
        Schema::dropIfExists('series');
        Schema::dropIfExists('topics');
        Schema::dropIfExists('donation_accounts');
        Schema::dropIfExists('speaker_user');
        Schema::dropIfExists('speakers');
        Schema::dropIfExists('venues');
        Schema::dropIfExists('institution_user');
        Schema::dropIfExists('institutions');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('states');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
    }
};
```

---

## If you want the 1000× ingestion + distribution layer (next migrations)
Tell me and I’ll add UUIDv7-style migrations for:
- `short_links` (QR + short URLs)
- `ingestion_jobs` (poster-to-event pipeline)
- `event_duplicates` (merge tracking)
- `endorsements` (institution ↔ speaker endorsements)
- `notification_deliveries` (proof of alerts sent)
