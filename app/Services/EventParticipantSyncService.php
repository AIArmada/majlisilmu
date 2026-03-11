<?php

namespace App\Services;

use App\Enums\EventParticipantRole;
use App\Models\Event;
use App\Models\EventParticipant;

class EventParticipantSyncService
{
    /**
     * @param  list<string>  $speakerIds
     * @param  list<array<string, mixed>>  $otherParticipants
     */
    public function sync(Event $event, array $speakerIds = [], array $otherParticipants = []): void
    {
        $event->participants()->delete();

        $order = 1;

        foreach ($this->normalizeSpeakerIds($speakerIds) as $speakerId) {
            EventParticipant::query()->create([
                'event_id' => $event->id,
                'speaker_id' => $speakerId,
                'role' => EventParticipantRole::Speaker->value,
                'order_column' => $order++,
                'is_public' => true,
            ]);
        }

        foreach ($this->normalizeParticipants($otherParticipants) as $participant) {
            EventParticipant::query()->create([
                'event_id' => $event->id,
                'speaker_id' => $participant['speaker_id'],
                'role' => $participant['role'],
                'name' => $participant['name'],
                'order_column' => $order++,
                'is_public' => $participant['is_public'],
                'notes' => $participant['notes'],
            ]);
        }
    }

    /**
     * @param  list<string|int|mixed>  $speakerIds
     * @return list<string>
     */
    protected function normalizeSpeakerIds(array $speakerIds): array
    {
        return collect($speakerIds)
            ->filter(fn (mixed $speakerId): bool => is_string($speakerId) && $speakerId !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $participants
     * @return list<array{role: string, speaker_id: ?string, name: ?string, is_public: bool, notes: ?string}>
     */
    protected function normalizeParticipants(array $participants): array
    {
        return collect($participants)
            ->map(function (mixed $participant): ?array {
                $role = $participant['role'] ?? null;

                if (! is_string($role) || EventParticipantRole::tryFrom($role) === null || $role === EventParticipantRole::Speaker->value) {
                    return null;
                }

                $speakerId = is_string($participant['speaker_id'] ?? null) && $participant['speaker_id'] !== ''
                    ? $participant['speaker_id']
                    : null;

                $name = is_string($participant['name'] ?? null)
                    ? trim($participant['name'])
                    : null;

                if ($speakerId === null && ($name === null || $name === '')) {
                    return null;
                }

                return [
                    'role' => $role,
                    'speaker_id' => $speakerId,
                    'name' => $name !== '' ? $name : null,
                    'is_public' => (bool) ($participant['is_public'] ?? true),
                    'notes' => is_string($participant['notes'] ?? null) && trim($participant['notes']) !== ''
                        ? trim($participant['notes'])
                        : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
