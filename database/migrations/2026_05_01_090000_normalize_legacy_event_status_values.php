<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $statusMapping = [
            'App\\States\\EventStatus\\Draft' => 'draft',
            'App\\States\\EventStatus\\Pending' => 'pending',
            'App\\States\\EventStatus\\Approved' => 'approved',
            'App\\States\\EventStatus\\NeedsChanges' => 'needs_changes',
            'App\\States\\EventStatus\\Rejected' => 'rejected',
            'App\\States\\EventStatus\\Cancelled' => 'cancelled',
        ];

        foreach ($statusMapping as $legacyValue => $normalizedValue) {
            DB::table('events')
                ->where('status', $legacyValue)
                ->update(['status' => $normalizedValue]);
        }
    }
};