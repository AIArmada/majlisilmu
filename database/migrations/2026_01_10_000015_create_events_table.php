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

            $table->uuid('institution_id')->nullable()->index();
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

            $table->string('language')->nullable()->index();
            $table->string('genre')->nullable()->index();
            $table->string('audience')->nullable()->index();

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
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
