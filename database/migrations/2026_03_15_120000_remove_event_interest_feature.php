<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('affiliate_conversions')) {
            DB::table('affiliate_conversions')
                ->where('conversion_type', 'event_interest')
                ->delete();
        }

        if (Schema::hasTable('event_interests')) {
            Schema::drop('event_interests');
        }

        if (Schema::hasColumn('events', 'interests_count')) {
            Schema::table('events', function ($table): void {
                $table->dropColumn('interests_count');
            });
        }
    }
};
