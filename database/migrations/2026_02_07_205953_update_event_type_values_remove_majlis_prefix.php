<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update event_type values to remove 'majlis_' prefix
        DB::table('events')
            ->where('event_type', 'majlis_zikir')
            ->update(['event_type' => 'zikir']);

        DB::table('events')
            ->where('event_type', 'majlis_selawat')
            ->update(['event_type' => 'selawat']);

        DB::table('events')
            ->where('event_type', 'majlis_tilawah')
            ->update(['event_type' => 'tilawah']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore 'majlis_' prefix
        DB::table('events')
            ->where('event_type', 'zikir')
            ->update(['event_type' => 'majlis_zikir']);

        DB::table('events')
            ->where('event_type', 'selawat')
            ->update(['event_type' => 'majlis_selawat']);

        DB::table('events')
            ->where('event_type', 'tilawah')
            ->update(['event_type' => 'majlis_tilawah']);
    }
};
