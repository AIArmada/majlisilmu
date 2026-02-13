<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->index();
            $table->foreignUuid('recurrence_rule_id')->nullable()->index();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('timezone')->default('Asia/Kuala_Lumpur');

            $table->string('status')->default('scheduled')->index();
            $table->boolean('is_generated')->default(false)->index();
            $table->unsignedInteger('capacity')->nullable();

            $table->string('timing_mode')->default('absolute');
            $table->string('prayer_reference')->nullable();
            $table->string('prayer_offset')->nullable();
            $table->string('prayer_display_text')->nullable();

            $table->timestamps();

            $table->index(['event_id', 'status', 'starts_at'], 'event_sessions_event_status_start');
            $table->index(['event_id', 'starts_at'], 'event_sessions_event_start');
        });
    }
};
