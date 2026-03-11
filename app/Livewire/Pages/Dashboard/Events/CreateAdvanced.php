<?php

namespace App\Livewire\Pages\Dashboard\Events;

use App\Enums\EventFormat;
use App\Enums\EventStructure;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\RegistrationMode;
use App\Enums\ScheduleKind;
use App\Enums\ScheduleState;
use App\Models\Event;
use App\Models\EventSubmission;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('Create Advanced Event')]
class CreateAdvanced extends Component
{
    /**
     * @var array<string, mixed>
     */
    public array $form = [];

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
        abort_unless($this->hasBuilderAccess(), 403);

        $defaultOrganizerType = $this->memberInstitutions()->isNotEmpty() ? 'institution' : 'speaker';
        $defaultOrganizerId = $defaultOrganizerType === 'institution'
            ? $this->memberInstitutions()->first()?->id
            : $this->memberSpeakers()->first()?->id;

        $this->form = [
            'title' => '',
            'description' => '',
            'timezone' => 'Asia/Kuala_Lumpur',
            'organizer_type' => $defaultOrganizerType,
            'organizer_id' => $defaultOrganizerId,
            'location_institution_id' => $defaultOrganizerType === 'institution' ? $defaultOrganizerId : $this->memberInstitutions()->first()?->id,
            'default_event_type' => EventType::KuliahCeramah->value,
            'default_event_format' => EventFormat::Physical->value,
            'visibility' => EventVisibility::Public->value,
            'registration_required' => true,
            'registration_mode' => RegistrationMode::Event->value,
            'children' => [$this->defaultChildFormState()],
        ];
    }

    public function addChild(): void
    {
        $this->form['children'][] = $this->defaultChildFormState(count($this->form['children']) + 1);
    }

    public function removeChild(int $index): void
    {
        unset($this->form['children'][$index]);
        $this->form['children'] = array_values($this->form['children']);
    }

    public function updatedFormOrganizerType(string $value): void
    {
        if ($value === 'institution') {
            $organizerId = $this->memberInstitutions()->first()?->id;
            $this->form['organizer_id'] = $organizerId;
            $this->form['location_institution_id'] = $organizerId;

            return;
        }

        $this->form['organizer_id'] = $this->memberSpeakers()->first()?->id;

        if (! filled($this->form['location_institution_id'] ?? null)) {
            $this->form['location_institution_id'] = $this->memberInstitutions()->first()?->id;
        }
    }

    public function submit(): mixed
    {
        $validated = $this->validate($this->rules());

        $user = $this->currentUser();

        abort_unless($user !== null, 403);

        $timezone = (string) $validated['form']['timezone'];
        $organizerType = (string) $validated['form']['organizer_type'];
        $organizerId = (string) $validated['form']['organizer_id'];

        $this->ensureOrganizerIsMemberOwned($user, $organizerType, $organizerId);

        $locationInstitutionId = $this->resolveLocationInstitutionId($user, $organizerType, $organizerId, $validated['form']['location_institution_id'] ?? null);

        /** @var list<array<string, mixed>> $childForms */
        $childForms = is_array($validated['form']['children']) ? $validated['form']['children'] : [];

        $childPayloads = collect($childForms)
            ->map(fn (array $child): array => $this->normalizeChildPayload($child, $timezone, $validated['form']))
            ->values();

        $parentStartsAt = $childPayloads->min('starts_at');
        $parentEndsAt = $childPayloads->max('ends_at');

        if (! $parentStartsAt instanceof Carbon || ! $parentEndsAt instanceof Carbon) {
            throw new \RuntimeException('Advanced event children must include valid schedule values.');
        }

        try {
            $parentEvent = DB::transaction(function () use ($user, $validated, $timezone, $organizerType, $organizerId, $locationInstitutionId, $childPayloads, $parentStartsAt, $parentEndsAt): Event {
                $parentEvent = Event::query()->create([
                    'user_id' => $user->id,
                    'submitter_id' => $user->id,
                    'parent_event_id' => null,
                    'event_structure' => EventStructure::ParentProgram->value,
                    'title' => (string) $validated['form']['title'],
                    'slug' => Str::slug((string) $validated['form']['title']).'-'.Str::lower(Str::random(7)),
                    'description' => (string) ($validated['form']['description'] ?? ''),
                    'starts_at' => $parentStartsAt,
                    'ends_at' => $parentEndsAt,
                    'timezone' => $timezone,
                    'institution_id' => $locationInstitutionId,
                    'organizer_type' => $this->organizerMorphClass($organizerType),
                    'organizer_id' => $organizerId,
                    'event_type' => [(string) $validated['form']['default_event_type']],
                    'event_format' => (string) $validated['form']['default_event_format'],
                    'visibility' => (string) $validated['form']['visibility'],
                    'schedule_kind' => ScheduleKind::CustomChain->value,
                    'schedule_state' => ScheduleState::Active->value,
                    'status' => 'draft',
                    'is_active' => true,
                ]);

                EventSubmission::query()->create([
                    'event_id' => $parentEvent->id,
                    'submitted_by' => $user->id,
                    'submitter_name' => $user->name,
                    'notes' => null,
                ]);

                foreach ($childPayloads as $childPayload) {
                    $childEvent = Event::query()->create([
                        'user_id' => $user->id,
                        'submitter_id' => $user->id,
                        'parent_event_id' => $parentEvent->id,
                        'event_structure' => EventStructure::ChildEvent->value,
                        'title' => $childPayload['title'],
                        'slug' => Str::slug($childPayload['title']).'-'.Str::lower(Str::random(7)),
                        'description' => $childPayload['description'],
                        'starts_at' => $childPayload['starts_at'],
                        'ends_at' => $childPayload['ends_at'],
                        'timezone' => $timezone,
                        'institution_id' => $locationInstitutionId,
                        'organizer_type' => $this->organizerMorphClass($organizerType),
                        'organizer_id' => $organizerId,
                        'event_type' => [$childPayload['event_type']],
                        'event_format' => $childPayload['event_format'],
                        'visibility' => (string) $validated['form']['visibility'],
                        'schedule_kind' => ScheduleKind::Single->value,
                        'schedule_state' => ScheduleState::Active->value,
                        'status' => 'draft',
                        'is_active' => true,
                    ]);

                    $childEvent->settings()->create([
                        'registration_required' => (bool) $validated['form']['registration_required'],
                        'registration_mode' => (string) $validated['form']['registration_mode'],
                    ]);
                }

                return $parentEvent;
            });
        } catch (Throwable $throwable) {
            report($throwable);

            $this->addError('form.title', __('The advanced event could not be created. Please try again.'));

            return null;
        }

        return redirect()->to(\App\Filament\Ahli\Resources\Events\EventResource::getUrl('edit', ['record' => $parentEvent], panel: 'ahli'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'form.title' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string'],
            'form.timezone' => ['required', 'string', 'max:64'],
            'form.organizer_type' => ['required', Rule::in(['institution', 'speaker'])],
            'form.organizer_id' => ['required', 'string'],
            'form.location_institution_id' => ['nullable', 'string'],
            'form.default_event_type' => ['required', Rule::in(array_column(EventType::cases(), 'value'))],
            'form.default_event_format' => ['required', Rule::in(array_column(EventFormat::cases(), 'value'))],
            'form.visibility' => ['required', Rule::in(array_column(EventVisibility::cases(), 'value'))],
            'form.registration_required' => ['required', 'boolean'],
            'form.registration_mode' => ['required', Rule::in(array_column(RegistrationMode::cases(), 'value'))],
            'form.children' => ['required', 'array', 'min:1'],
            'form.children.*.title' => ['required', 'string', 'max:255'],
            'form.children.*.description' => ['nullable', 'string'],
            'form.children.*.starts_at' => ['required', 'date'],
            'form.children.*.ends_at' => ['nullable', 'date'],
            'form.children.*.event_format' => ['nullable', Rule::in(array_column(EventFormat::cases(), 'value'))],
            'form.children.*.event_type' => ['nullable', Rule::in(array_column(EventType::cases(), 'value'))],
        ];
    }

    /**
     * @return array{title: string, description: string, starts_at: string, ends_at: string, event_format: null, event_type: null}
     */
    protected function defaultChildFormState(int $position = 1): array
    {
        $startsAt = now()->addDays($position)->setTime(20, 0);

        return [
            'title' => '',
            'description' => '',
            'starts_at' => $startsAt->format('Y-m-d\TH:i'),
            'ends_at' => $startsAt->copy()->addHours(2)->format('Y-m-d\TH:i'),
            'event_format' => null,
            'event_type' => null,
        ];
    }

    protected function hasBuilderAccess(): bool
    {
        return $this->memberInstitutions()->isNotEmpty() || $this->memberSpeakers()->isNotEmpty();
    }

    /**
     * @return Collection<int, Institution>
     */
    protected function memberInstitutions(): Collection
    {
        $user = $this->currentUser();

        if (! $user instanceof User) {
            return collect();
        }

        return $user->institutions()
            ->whereIn('status', ['verified', 'pending'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Speaker>
     */
    protected function memberSpeakers(): Collection
    {
        $user = $this->currentUser();

        if (! $user instanceof User) {
            return collect();
        }

        return $user->speakers()
            ->whereIn('status', ['verified', 'pending'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    protected function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    protected function ensureOrganizerIsMemberOwned(User $user, string $organizerType, string $organizerId): void
    {
        unset($user);

        $allowed = match ($organizerType) {
            'institution' => $this->memberInstitutions()->pluck('id')->contains($organizerId),
            'speaker' => $this->memberSpeakers()->pluck('id')->contains($organizerId),
            default => false,
        };

        if (! $allowed) {
            abort(403);
        }
    }

    protected function resolveLocationInstitutionId(User $user, string $organizerType, string $organizerId, mixed $locationInstitutionId): ?string
    {
        unset($user);

        if ($organizerType === 'institution') {
            return $organizerId;
        }

        if (! is_string($locationInstitutionId) || $locationInstitutionId === '') {
            return null;
        }

        $allowedInstitutionIds = $this->memberInstitutions()->pluck('id');

        if (! $allowedInstitutionIds->contains($locationInstitutionId)) {
            abort(403);
        }

        return $locationInstitutionId;
    }

    /**
     * @param  array<string, mixed>  $child
     * @param  array<string, mixed>  $parentForm
     * @return array{title: string, description: string, starts_at: Carbon, ends_at: Carbon, event_type: string, event_format: string}
     */
    protected function normalizeChildPayload(array $child, string $timezone, array $parentForm): array
    {
        $startsAt = Carbon::parse((string) $child['starts_at'], $timezone)->utc();
        $endsAt = filled($child['ends_at'] ?? null)
            ? Carbon::parse((string) $child['ends_at'], $timezone)->utc()
            : $startsAt->copy()->addHours(2);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'form.children' => __('Each child event must end after it starts.'),
            ]);
        }

        return [
            'title' => trim((string) $child['title']),
            'description' => trim((string) ($child['description'] ?? '')),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'event_type' => (string) ($child['event_type'] ?: $parentForm['default_event_type']),
            'event_format' => (string) ($child['event_format'] ?: $parentForm['default_event_format']),
        ];
    }

    protected function organizerMorphClass(string $organizerType): string
    {
        return $organizerType === 'institution' ? Institution::class : Speaker::class;
    }

    public function render(): View
    {
        return view('livewire.pages.dashboard.events.create-advanced', [
            'institutionOptions' => $this->memberInstitutions()->pluck('name', 'id')->all(),
            'speakerOptions' => $this->memberSpeakers()->pluck('name', 'id')->all(),
            'eventTypeOptions' => collect(EventType::cases())->mapWithKeys(fn (EventType $type): array => [$type->value => $type->getLabel()])->all(),
            'eventFormatOptions' => collect(EventFormat::cases())->mapWithKeys(fn (EventFormat $format): array => [$format->value => $format->label()])->all(),
            'visibilityOptions' => collect(EventVisibility::cases())->mapWithKeys(fn (EventVisibility $visibility): array => [$visibility->value => $visibility->getLabel()])->all(),
        ]);
    }
}
