<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_checkins')) {
            return;
        }

        Schema::create('event_checkins', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->index();
            $table->foreignUuid('event_session_id')->nullable()->index();
            $table->foreignUuid('registration_id')->nullable()->index();
            $table->foreignUuid('user_id')->index();
            $table->foreignUuid('verified_by_user_id')->nullable()->index();

            $table->string('method', 40)->default('self_reported')->index();
            $table->timestamp('checked_in_at')->index();

            $table->decimal('lat', 10, 7)->nullable()->index();
            $table->decimal('lng', 10, 7)->nullable()->index();
            $table->decimal('accuracy_m', 8, 2)->nullable();

            $table->timestamps();

            $table->index(['event_id', 'user_id'], 'event_checkins_event_user_idx');
            $table->index(['event_id', 'event_session_id', 'user_id'], 'event_checkins_event_session_user_idx');
        });
    }
};
