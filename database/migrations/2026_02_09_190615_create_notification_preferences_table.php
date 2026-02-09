<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('owner_type')->index();
                $table->foreignUuid('owner_id')->index();
                $table->string('notification_key')->index();
                $table->boolean('enabled')->default(true)->index();
                $table->string('frequency')->default('instant')->index();
                $table->jsonb('channels')->nullable();
                $table->time('quiet_hours_start')->nullable();
                $table->time('quiet_hours_end')->nullable();
                $table->string('timezone')->nullable();
                $table->jsonb('meta')->nullable();
                $table->timestamps();

                $table->unique(
                    ['owner_type', 'owner_id', 'notification_key'],
                    'notification_preferences_owner_notification_key_unique'
                );
                $table->index(
                    ['owner_type', 'owner_id', 'enabled'],
                    'notification_preferences_owner_enabled'
                );
            });
        }
    }
};
