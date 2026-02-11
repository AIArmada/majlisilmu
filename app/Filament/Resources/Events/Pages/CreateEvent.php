<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\Tag;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    #[\Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset(
            $data['languages'],
            $data['domain_tags'],
            $data['discipline_tags'],
            $data['source_tags'],
            $data['issue_tags'],
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncRelationState($this->record, $this->form->getState());
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function syncRelationState(Event $event, array $state): void
    {
        $languageIds = collect($state['languages'] ?? [])
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $event->syncLanguages($languageIds);

        $tagIds = collect([
            ...($state['domain_tags'] ?? []),
            ...($state['discipline_tags'] ?? []),
            ...($state['source_tags'] ?? []),
            ...($state['issue_tags'] ?? []),
        ])
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        $tags = Tag::query()->whereKey($tagIds)->get();

        $event->syncTags($tags);
    }
}
