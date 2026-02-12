<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\Tag;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

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
        $this->syncRelationState($this->eventRecord(), $this->form->getState());
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function syncRelationState(Event $event, array $state): void
    {
        $rawLanguageIds = is_array($state['languages'] ?? null) ? $state['languages'] : [];

        $languageIds = collect($rawLanguageIds)
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $event->syncLanguages($languageIds);

        $domainTagIds = is_array($state['domain_tags'] ?? null) ? $state['domain_tags'] : [];
        $disciplineTagIds = is_array($state['discipline_tags'] ?? null) ? $state['discipline_tags'] : [];
        $sourceTagIds = is_array($state['source_tags'] ?? null) ? $state['source_tags'] : [];
        $issueTagIds = is_array($state['issue_tags'] ?? null) ? $state['issue_tags'] : [];

        $tagIds = collect(array_merge($domainTagIds, $disciplineTagIds, $sourceTagIds, $issueTagIds))
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        $tags = Tag::query()->whereKey($tagIds)->get();

        $event->syncTags($tags);
    }

    protected function eventRecord(): Event
    {
        $record = $this->getRecord();

        if (! $record instanceof Event) {
            throw new \RuntimeException('Expected Filament record to be an Event instance.');
        }

        return $record;
    }
}
