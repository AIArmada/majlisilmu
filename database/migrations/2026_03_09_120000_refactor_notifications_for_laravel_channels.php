<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table): void {
                if (! Schema::hasColumn('notifications', 'family')) {
                    $table->string('family')->nullable()->index();
                }

                if (! Schema::hasColumn('notifications', 'trigger')) {
                    $table->string('trigger')->nullable()->index();
                }

                if (! Schema::hasColumn('notifications', 'priority')) {
                    $table->string('priority')->nullable()->index();
                }

                if (! Schema::hasColumn('notifications', 'fingerprint')) {
                    $table->string('fingerprint')->nullable()->index();
                }

                if (! Schema::hasColumn('notifications', 'action_url')) {
                    $table->text('action_url')->nullable();
                }

                if (! Schema::hasColumn('notifications', 'entity_type')) {
                    $table->string('entity_type')->nullable()->index();
                }

                if (! Schema::hasColumn('notifications', 'entity_id')) {
                    $table->foreignUuid('entity_id')->nullable()->index();
                }

                if (! Schema::hasColumn('notifications', 'occurred_at')) {
                    $table->timestamp('occurred_at')->nullable()->index();
                }

                if (! Schema::hasColumn('notifications', 'inbox_visible')) {
                    $table->boolean('inbox_visible')->default(true)->index();
                }

                if (! Schema::hasColumn('notifications', 'is_digest')) {
                    $table->boolean('is_digest')->default(false)->index();
                }
            });

            $connection = Schema::getConnection();
            $driver = $connection->getDriverName();

            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
            }
        }

        if (Schema::hasTable('notification_messages')) {
            Schema::table('notification_messages', function (Blueprint $table): void {
                if (! Schema::hasColumn('notification_messages', 'delivery_cadence')) {
                    $table->string('delivery_cadence')->nullable()->index();
                }

                if (! Schema::hasColumn('notification_messages', 'processed_at')) {
                    $table->timestamp('processed_at')->nullable()->index();
                }
            });
        }
    }
};
