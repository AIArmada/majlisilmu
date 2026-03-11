<?php

use App\Enums\EventStructure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('events', 'parent_event_id') || ! Schema::hasColumn('events', 'event_structure')) {
            Schema::table('events', function (Blueprint $table): void {
                if (! Schema::hasColumn('events', 'parent_event_id')) {
                    $table->foreignUuid('parent_event_id')->nullable()->after('space_id')->index();
                }

                if (! Schema::hasColumn('events', 'event_structure')) {
                    $table->string('event_structure')->default(EventStructure::Standalone->value)->after('slug')->index();
                }
            });
        }

        DB::table('events')
            ->whereNull('event_structure')
            ->update(['event_structure' => EventStructure::Standalone->value]);
    }
};
