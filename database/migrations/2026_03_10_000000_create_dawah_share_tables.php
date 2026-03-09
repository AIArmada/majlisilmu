<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dawah_share_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->index();
            $table->string('subject_type', 32)->index();
            $table->foreignUuid('subject_id')->nullable()->index();
            $table->string('subject_key')->index();
            $table->string('destination_url');
            $table->string('canonical_url');
            $table->string('share_token', 64)->unique();
            $table->string('title_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_shared_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'canonical_url'], 'dawah_share_links_user_canonical_unique');
            $table->index(['user_id', 'subject_type'], 'dawah_share_links_user_subject_type_index');
        });

        Schema::create('dawah_share_attributions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('link_id')->index();
            $table->foreignUuid('user_id')->index();
            $table->string('visitor_key', 64)->index();
            $table->string('cookie_value', 64)->unique();
            $table->string('landing_url')->nullable();
            $table->string('referrer_url')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent')->nullable();
            $table->foreignUuid('signed_up_user_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('first_seen_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'visitor_key'], 'dawah_share_attributions_user_visitor_index');
        });

        Schema::create('dawah_share_visits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('link_id')->index();
            $table->foreignUuid('attribution_id')->index();
            $table->string('visitor_key', 64)->index();
            $table->string('visited_url');
            $table->string('subject_type', 32)->index();
            $table->foreignUuid('subject_id')->nullable()->index();
            $table->string('subject_key')->index();
            $table->string('visit_kind', 24)->index();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();

            $table->index(['link_id', 'visitor_key', 'occurred_at'], 'dawah_share_visits_link_visitor_occurred_index');
        });

        Schema::create('dawah_share_outcomes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('link_id')->index();
            $table->foreignUuid('attribution_id')->index();
            $table->foreignUuid('sharer_user_id')->index();
            $table->foreignUuid('actor_user_id')->nullable()->index();
            $table->string('outcome_type', 48)->index();
            $table->string('subject_type', 32)->index();
            $table->foreignUuid('subject_id')->nullable()->index();
            $table->string('subject_key')->index();
            $table->string('outcome_key')->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();

            $table->index(['link_id', 'outcome_type'], 'dawah_share_outcomes_link_type_index');
        });
    }
};
