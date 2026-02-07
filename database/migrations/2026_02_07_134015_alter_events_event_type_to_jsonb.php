<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Converts event_type from a plain string column to jsonb.
     * Existing string values are wrapped into single-element JSON arrays.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL: Convert existing string values to JSON arrays
            DB::statement(<<<'SQL'
                UPDATE events
                SET event_type = CASE
                    WHEN event_type IS NULL THEN '[]'::text
                    WHEN event_type::text LIKE '[%' THEN event_type::text
                    ELSE jsonb_build_array(event_type)::text
                END
            SQL);

            // Alter the column type to jsonb
            Schema::table('events', function (Blueprint $table) {
                $table->jsonb('event_type')->default('[]')->change();
            });
        } else {
            // SQLite / MySQL: Convert string values to JSON arrays
            $events = DB::table('events')->whereNotNull('event_type')->get(['id', 'event_type']);
            foreach ($events as $event) {
                $value = $event->event_type;
                if (! str_starts_with($value, '[')) {
                    DB::table('events')->where('id', $event->id)->update([
                        'event_type' => json_encode([$value]),
                    ]);
                }
            }

            DB::table('events')->whereNull('event_type')->update(['event_type' => '[]']);
        }
    }
};
