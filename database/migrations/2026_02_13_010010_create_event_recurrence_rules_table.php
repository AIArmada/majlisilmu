<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_recurrence_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->index();

            $table->string('frequency')->index();
            $table->unsignedInteger('interval')->default(1);
            $table->jsonb('by_weekdays')->nullable();
            $table->unsignedInteger('by_month_day')->nullable();

            $table->date('start_date');
            $table->date('until_date')->nullable();
            $table->unsignedInteger('occurrence_count')->nullable();

            $table->time('starts_time')->nullable();
            $table->time('ends_time')->nullable();
            $table->string('timezone')->default('Asia/Kuala_Lumpur');

            $table->string('timing_mode')->default('absolute');
            $table->string('prayer_reference')->nullable();
            $table->string('prayer_offset')->nullable();
            $table->string('prayer_display_text')->nullable();

            $table->string('status')->default('active')->index();
            $table->date('generated_until')->nullable();

            $table->timestamps();

            $table->index(['event_id', 'status'], 'event_recurrence_event_status');
        });
    }
};
