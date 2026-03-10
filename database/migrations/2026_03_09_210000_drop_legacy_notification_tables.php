<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_preferences')) {
            Schema::drop('notification_preferences');
        }

        if (Schema::hasTable('notification_endpoints')) {
            Schema::drop('notification_endpoints');
        }
    }
};
