<?php

use App\Enums\RegistrationMode;
use App\Enums\ScheduleKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('events')) {
            DB::table('events')
                ->where('schedule_kind', 'recurring')
                ->update(['schedule_kind' => ScheduleKind::Single->value]);
        }

        if (Schema::hasTable('event_settings') && Schema::hasColumn('event_settings', 'registration_mode')) {
            DB::table('event_settings')
                ->where('registration_mode', 'session')
                ->update(['registration_mode' => RegistrationMode::Event->value]);
        }

        if (Schema::hasTable('registrations') && Schema::hasColumn('registrations', 'event_session_id')) {
            Schema::table('registrations', function (Blueprint $table): void {
                $table->dropIndex('registrations_event_session_email_idx');
                $table->dropIndex('registrations_event_session_phone_idx');
                $table->dropColumn('event_session_id');
            });
        }

        if (Schema::hasTable('event_checkins') && Schema::hasColumn('event_checkins', 'event_session_id')) {
            Schema::table('event_checkins', function (Blueprint $table): void {
                $table->dropIndex('event_checkins_event_session_user_idx');
                $table->dropColumn('event_session_id');
            });
        }

        if (Schema::hasTable('event_recurrence_rules')) {
            Schema::drop('event_recurrence_rules');
        }

        if (Schema::hasTable('event_sessions')) {
            Schema::drop('event_sessions');
        }
    }
};
