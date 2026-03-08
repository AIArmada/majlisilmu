<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->index();
            $table->foreignUuid('event_session_id')->nullable()->index();
            $table->foreignUuid('user_id')->nullable()->index();

            $table->string('name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();

            $table->string('status')->default('registered')->index();

            $table->string('checkin_token', 64)->nullable()->unique();

            $table->timestamps();

            $table->index(['event_id', 'event_session_id', 'email'], 'registrations_event_session_email_idx');
            $table->index(['event_id', 'event_session_id', 'phone'], 'registrations_event_session_phone_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
