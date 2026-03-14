<?php

namespace App\Filament\Resources\Events\Pages;

use App\Actions\Events\SyncEventResourceRelationsAction;
use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Support\Events\AdminEventTimeMapper;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    #[\Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = AdminEventTimeMapper::normalizeForPersistence($data);

        unset(
            $data['languages'],
            $data['domain_tags'],
            $data['discipline_tags'],
            $data['source_tags'],
            $data['issue_tags'],
            $data['registration_mode'],
            $data['speakers'],
            $data['other_key_people'],
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        app(SyncEventResourceRelationsAction::class)->handle(
            $this->eventRecord(),
            $this->form->getState(),
            lockRegistrationMode: false,
            syncKeyPeople: true,
        );
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
