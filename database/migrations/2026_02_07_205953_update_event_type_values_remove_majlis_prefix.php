<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Updates event_type values to remove 'majlis_' prefix.
     * Handles both plain string columns and jsonb array columns.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        $replacements = [
            'majlis_zikir' => 'zikir',
            'majlis_selawat' => 'selawat',
            'majlis_tilawah' => 'tilawah',
        ];

        if ($driver === 'pgsql') {
            // For jsonb columns: replace values within JSON arrays using text replacement
            foreach ($replacements as $old => $new) {
                DB::statement(
                    'UPDATE events SET event_type = REPLACE(event_type::text, ?, ?)::jsonb WHERE event_type::text LIKE ?',
                    [$old, $new, "%{$old}%"]
                );
            }
        } else {
            // SQLite / MySQL: iterate and replace within JSON
            $events = DB::table('events')
                ->whereNotNull('event_type')
                ->get(['id', 'event_type']);

            foreach ($events as $event) {
                $types = json_decode($event->event_type, true);
                if (! is_array($types)) {
                    continue;
                }

                $changed = false;
                foreach ($types as &$type) {
                    if (isset($replacements[$type])) {
                        $type = $replacements[$type];
                        $changed = true;
                    }
                }
                unset($type);

                if ($changed) {
                    DB::table('events')
                        ->where('id', $event->id)
                        ->update(['event_type' => json_encode($types)]);
                }
            }
        }
    }
};
