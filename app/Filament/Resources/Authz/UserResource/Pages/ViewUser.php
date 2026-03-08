<?php

declare(strict_types=1);

namespace App\Filament\Resources\Authz\UserResource\Pages;

use App\Filament\Resources\Authz\UserResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Institutions\InstitutionResource;
use App\Filament\Resources\References\ReferenceResource;
use App\Filament\Resources\Speakers\SpeakerResource;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected string $view = 'filament.resources.authz.user-resource.pages.view-user';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->loadMissing([
            'roles',
            'savedEvents' => fn ($query) => $query
                ->with(['institution:id,name', 'venue:id,name'])
                ->orderBy('event_saves.created_at', 'desc'),
            'interestedEvents' => fn ($query) => $query
                ->with(['institution:id,name', 'venue:id,name'])
                ->orderBy('event_interests.created_at', 'desc'),
            'goingEvents' => fn ($query) => $query
                ->with(['institution:id,name', 'venue:id,name'])
                ->orderBy('event_attendees.created_at', 'desc'),
            'eventCheckins' => fn ($query) => $query
                ->with(['event:id,title,status,starts_at', 'verifiedBy:id,name'])
                ->orderByDesc('checked_in_at'),
            'registrations' => fn ($query) => $query
                ->with(['event:id,title,status,starts_at', 'session:id,title,event_id'])
                ->latest(),
            'followingInstitutions' => fn ($query) => $query->orderBy('name'),
            'followingSpeakers' => fn ($query) => $query->orderBy('name'),
            'followingReferences' => fn ($query) => $query->orderBy('title'),
            'eventSubmissions' => fn ($query) => $query
                ->with(['event:id,title,status,starts_at'])
                ->latest(),
            'institutions' => fn ($query) => $query->orderBy('name'),
            'speakers' => fn ($query) => $query->orderBy('name'),
            'memberEvents' => fn ($query) => $query
                ->with(['institution:id,name', 'venue:id,name'])
                ->orderByDesc('starts_at'),
            'savedSearches' => fn ($query) => $query->latest(),
        ]);
    }

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    /**
     * @return list<string>
     */
    public function roleNames(): array
    {
        /** @var list<string> $roles */
        $roles = $this->userRecord()->roles
            ->pluck('name')
            ->filter(fn (mixed $name): bool => filled($name))
            ->sort()
            ->values()
            ->all();

        return $roles;
    }

    public function eventUrl(?Event $event): ?string
    {
        return $event ? EventResource::getUrl('view', ['record' => $event]) : null;
    }

    public function institutionUrl(?Institution $institution): ?string
    {
        return $institution ? InstitutionResource::getUrl('view', ['record' => $institution]) : null;
    }

    public function speakerUrl(?Speaker $speaker): ?string
    {
        return $speaker ? SpeakerResource::getUrl('view', ['record' => $speaker]) : null;
    }

    public function referenceUrl(?Reference $reference): ?string
    {
        return $reference ? ReferenceResource::getUrl('edit', ['record' => $reference]) : null;
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    public function formatSavedSearchFilters(?array $filters): string
    {
        if (! is_array($filters) || $filters === []) {
            return '-';
        }

        $parts = collect($filters)
            ->filter(fn (mixed $value): bool => filled($value) || (is_array($value) && $value !== []))
            ->map(function (mixed $value, string $key): string {
                $formattedValue = match (true) {
                    is_array($value) => implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value)),
                    is_bool($value) => $value ? 'Yes' : 'No',
                    default => (string) $value,
                };

                return Str::headline($key).': '.$formattedValue;
            })
            ->values()
            ->all();

        if ($parts === []) {
            return '-';
        }

        return implode(' | ', array_slice($parts, 0, 5));
    }

    public function formattedSearchNotify(?string $state): string
    {
        return filled($state) ? Str::headline($state) : '-';
    }

    public function eventStatusBadgeColor(?string $state): string
    {
        return match ($state) {
            'approved' => 'success',
            'pending', 'needs_changes' => 'warning',
            'cancelled', 'rejected' => 'danger',
            'draft' => 'gray',
            default => 'gray',
        };
    }

    public function entityStatusBadgeColor(?string $state): string
    {
        return match ($state) {
            'verified' => 'success',
            'pending' => 'warning',
            'rejected' => 'danger',
            'unverified' => 'gray',
            default => 'gray',
        };
    }

    public function registrationStatusBadgeColor(?string $state): string
    {
        return match ($state) {
            'registered', 'attended' => 'success',
            'cancelled', 'no_show' => 'danger',
            default => 'gray',
        };
    }

    public function humanLabel(?string $value): string
    {
        return filled($value) ? Str::headline($value) : '-';
    }

    private function userRecord(): User
    {
        /** @var User $user */
        $user = $this->getRecord();

        return $user;
    }
}
