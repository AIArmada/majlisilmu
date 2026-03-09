<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_messages')) {
            return;
        }

        Schema::table('notification_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('notification_messages', 'dispatched_at')) {
                $table->timestamp('dispatched_at')->nullable()->index();
            }

            if (! Schema::hasColumn('notification_messages', 'notification_id')) {
                $table->uuid('notification_id')->nullable()->index();
            }
        });
    }
};
