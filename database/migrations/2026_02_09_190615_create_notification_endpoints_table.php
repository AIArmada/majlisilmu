<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_endpoints')) {
            Schema::create('notification_endpoints', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('owner_type')->index();
                $table->foreignUuid('owner_id')->index();
                $table->string('channel')->index();
                $table->string('address')->nullable();
                $table->string('external_id')->nullable();
                $table->string('status')->default('active')->index();
                $table->boolean('is_primary')->default(false)->index();
                $table->timestamp('verified_at')->nullable();
                $table->jsonb('meta')->nullable();
                $table->timestamps();

                $table->unique(
                    ['owner_type', 'owner_id', 'channel', 'address'],
                    'notification_endpoints_owner_channel_address_unique'
                );
                $table->index(
                    ['owner_type', 'owner_id', 'channel', 'status'],
                    'notification_endpoints_owner_channel_status'
                );
            });
        }
    }
};
