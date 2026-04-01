<?php

namespace App\Filament\Ahli\Resources\Events\Pages;

use App\Filament\Ahli\Resources\Events\EventResource;
use App\Models\Event;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_child_event')
                ->label('Add Child Event')
                ->icon(Heroicon::OutlinedPlus)
                ->url(fn (): string => route('submit-event.create', ['parent' => $this->eventRecord()->getKey()]))
                ->visible(fn (): bool => $this->eventRecord()->isParentProgram()),
            Action::make('duplicate_event')
                ->label('Duplicate Event')
                ->url(fn (): string => route('submit-event.create', ['duplicate' => $this->eventRecord()->getKey()])),
            EditAction::make(),
            Action::make('view_public')
                ->label('View Public Page')
                ->icon(Heroicon::OutlinedEye)
                ->url(fn (): string => route('events.show', $this->eventRecord()))
                ->openUrlInNewTab(),
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
