<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->foreignUuid('event_session_id')->nullable()->index();

            $table->index(['event_id', 'event_session_id', 'email'], 'registrations_event_session_email_idx');
            $table->index(['event_id', 'event_session_id', 'phone'], 'registrations_event_session_phone_idx');
        });

        // Remove strict DB uniqueness; uniqueness is now enforced in application logic by registration mode.
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE registrations DROP CONSTRAINT IF EXISTS registrations_event_id_email_unique');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE registrations DROP INDEX registrations_event_id_email_unique');
        } elseif ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS registrations_event_id_email_unique');
        }
    }
};
