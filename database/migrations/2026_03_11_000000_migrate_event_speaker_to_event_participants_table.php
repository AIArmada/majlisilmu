<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_participants')) {
            Schema::create('event_participants', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('event_id')->index();
                $table->uuid('speaker_id')->nullable()->index();
                $table->string('role')->index();
                $table->string('name')->nullable();
                $table->unsignedSmallInteger('order_column')->default(0);
                $table->boolean('is_public')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['event_id', 'order_column']);
                $table->index(['event_id', 'role'], 'event_participants_event_role');
                $table->index(['event_id', 'speaker_id'], 'event_participants_event_speaker');
            });
        }

        if (! Schema::hasTable('event_speaker')) {
            return;
        }

        $existingSpeakerPairs = DB::table('event_participants')
            ->where('role', 'speaker')
            ->select(['event_id', 'speaker_id'])
            ->get()
            ->map(static fn (object $row): string => $row->event_id.'|'.$row->speaker_id)
            ->all();

        $existingSpeakerPairLookup = array_fill_keys($existingSpeakerPairs, true);

        $rowsToInsert = [];

        foreach (DB::table('event_speaker')->orderBy('event_id')->orderBy('order_column')->cursor() as $row) {
            $pairKey = $row->event_id.'|'.$row->speaker_id;

            if (isset($existingSpeakerPairLookup[$pairKey])) {
                continue;
            }

            $rowsToInsert[] = [
                'id' => (string) Str::uuid(),
                'event_id' => $row->event_id,
                'speaker_id' => $row->speaker_id,
                'role' => 'speaker',
                'name' => null,
                'order_column' => (int) ($row->order_column ?? 0),
                'is_public' => true,
                'notes' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];

            $existingSpeakerPairLookup[$pairKey] = true;
        }

        foreach (array_chunk($rowsToInsert, 500) as $chunk) {
            DB::table('event_participants')->insert($chunk);
        }

        Schema::dropIfExists('event_speaker');
    }
};
