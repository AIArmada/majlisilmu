<?php

namespace App\Filament\Ahli\Resources\Events\Pages;

use App\Enums\RegistrationMode;
use App\Enums\TagType;
use App\Filament\Ahli\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\Tag;
use App\Support\Events\AdminEventTimeMapper;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    #[\Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $event = $this->eventRecord();
        $event->loadMissing(['languages:id', 'tags:id,type']);

        $data['languages'] = $event->languages->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $data['domain_tags'] = $this->getTagIdsByType(TagType::Domain);
        $data['discipline_tags'] = $this->getTagIdsByType(TagType::Discipline);
        $data['source_tags'] = $this->getTagIdsByType(TagType::Source);
        $data['issue_tags'] = $this->getTagIdsByType(TagType::Issue);
        $data['registration_mode'] = $this->resolveRegistrationMode($event)->value;

        return AdminEventTimeMapper::injectFormTimeFields($data);
    }

    #[\Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = AdminEventTimeMapper::normalizeForPersistence($data);

        unset(
            $data['languages'],
            $data['domain_tags'],
            $data['discipline_tags'],
            $data['source_tags'],
            $data['issue_tags'],
            $data['registration_mode'],
        );

        return $data;
    }

    protected function afterSave(): void
    {
        $event = $this->eventRecord();
        $requestedRegistrationMode = (string) ($this->form->getState()['registration_mode'] ?? RegistrationMode::Event->value);
        $currentRegistrationMode = $this->resolveRegistrationMode($event)->value;
        $modeToPersist = $requestedRegistrationMode;

        if ($event->registrations()->exists() && $requestedRegistrationMode !== $currentRegistrationMode) {
            $modeToPersist = $currentRegistrationMode;
        }

        $event->settings()->updateOrCreate(
            ['event_id' => $event->id],
            ['registration_mode' => $modeToPersist]
        );

        $this->syncRelationState($event, $this->form->getState());
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

    /**
     * @return list<string>
     */
    protected function getTagIdsByType(TagType $type): array
    {
        return $this->eventRecord()->tags
            ->where('type', $type->value)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    protected function resolveRegistrationMode(Event $event): RegistrationMode
    {
        $rawMode = $event->settings?->registration_mode;

        if ($rawMode instanceof RegistrationMode) {
            return $rawMode;
        }

        if (is_string($rawMode)) {
            return RegistrationMode::tryFrom($rawMode) ?? RegistrationMode::Event;
        }

        return RegistrationMode::Event;
    }

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_public')
                ->label('View Public Page')
                ->icon(Heroicon::OutlinedEye)
                ->url(fn (): string => route('events.show', $this->eventRecord())),
        ];
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

