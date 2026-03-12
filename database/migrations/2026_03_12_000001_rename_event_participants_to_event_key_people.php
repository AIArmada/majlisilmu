<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_participants') && ! Schema::hasTable('event_key_people')) {
            Schema::rename('event_participants', 'event_key_people');
        }

        if (! Schema::hasTable('event_key_people')) {
            return;
        }

        Schema::table('event_key_people', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_key_people', 'role')) {
                return;
            }

            try {
                $table->dropIndex('event_participants_event_role');
            } catch (\Throwable) {
            }

            try {
                $table->dropIndex('event_participants_event_speaker');
            } catch (\Throwable) {
            }

            $table->index(['event_id', 'role'], 'event_key_people_event_role');
            $table->index(['event_id', 'speaker_id'], 'event_key_people_event_speaker');
        });
    }
};
