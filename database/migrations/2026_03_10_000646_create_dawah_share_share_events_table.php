<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dawah_share_share_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('link_id')->index();
            $table->foreignUuid('user_id')->index();
            $table->string('provider', 32)->index();
            $table->string('event_type', 32)->index();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();

            $table->index(['link_id', 'provider', 'event_type'], 'dawah_share_share_events_link_provider_type_index');
        });
    }
};
