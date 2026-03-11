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

    public int $activeStep = 1;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
        abort_unless($this->hasBuilderAccess(), 403);

        $requestedInstitutionId = request()->query('institution');
        $memberInstitutions = $this->memberInstitutions();
        $preferredInstitutionId = is_string($requestedInstitutionId) && $requestedInstitutionId !== '' && $memberInstitutions->pluck('id')->contains($requestedInstitutionId)
            ? $requestedInstitutionId
            : null;

        $defaultOrganizerType = $memberInstitutions->isNotEmpty() ? 'institution' : 'speaker';
        $defaultOrganizerId = $defaultOrganizerType === 'institution'
            ? $preferredInstitutionId ?: $memberInstitutions->first()?->id
            : $this->memberSpeakers()->first()?->id;

        $this->form = [
            'title' => '',
            'description' => '',
            'timezone' => 'Asia/Kuala_Lumpur',
            'program_starts_at' => now('Asia/Kuala_Lumpur')->addDays(2)->setTime(20, 0)->format('Y-m-d\TH:i'),
            'program_ends_at' => now('Asia/Kuala_Lumpur')->addDays(30)->setTime(22, 0)->format('Y-m-d\TH:i'),
            'organizer_type' => $defaultOrganizerType,
            'organizer_id' => $defaultOrganizerId,
            'location_institution_id' => $defaultOrganizerType === 'institution' ? $defaultOrganizerId : ($preferredInstitutionId ?: $memberInstitutions->first()?->id),
            'default_event_type' => EventType::KuliahCeramah->value,
            'default_event_format' => EventFormat::Physical->value,
            'visibility' => EventVisibility::Public->value,
            'registration_required' => true,
            'registration_mode' => RegistrationMode::Event->value,
        ];
    }

    public function goToStep(int $step): void
    {
        $this->activeStep = max(1, min(3, $step));
    }

    public function nextStep(): void
    {
        $this->goToStep($this->activeStep + 1);
    }

    public function previousStep(): void
    {
        $this->goToStep($this->activeStep - 1);
    }

    public function applyTemplate(string $template): void
    {
        $timezone = (string) ($this->form['timezone'] ?? 'Asia/Kuala_Lumpur');
        $startsAt = now($timezone)->addDays(2)->setTime(20, 0);

        $templateState = match ($template) {
            'weekly_series' => [
                'title' => $this->form['title'] ?: __('Weekly Knowledge Series'),
                'description' => $this->form['description'] ?: __('A repeating program with one featured child event every week.'),
                'program_starts_at' => $startsAt->copy()->format('Y-m-d\TH:i'),
                'program_ends_at' => $startsAt->copy()->addWeeks(4)->format('Y-m-d\TH:i'),
            ],
            'weekend_intensive' => [
                'title' => $this->form['title'] ?: __('Weekend Intensive Program'),
                'description' => $this->form['description'] ?: __('A compact multi-session program across one focused weekend.'),
                'program_starts_at' => $startsAt->copy()->next('Friday')->setTime(20, 30)->format('Y-m-d\TH:i'),
                'program_ends_at' => $startsAt->copy()->next('Sunday')->setTime(12, 30)->format('Y-m-d\TH:i'),
            ],
            'ramadan_program' => [
                'title' => $this->form['title'] ?: __('Ramadan Companion Program'),
                'description' => $this->form['description'] ?: __('An umbrella program with nightly child events and lighter weekend highlights.'),
                'program_starts_at' => $startsAt->copy()->setTime(21, 15)->format('Y-m-d\TH:i'),
                'program_ends_at' => $startsAt->copy()->addDays(10)->setTime(22, 30)->format('Y-m-d\TH:i'),
            ],
            default => null,
        };

        if (! is_array($templateState)) {
            return;
        }

        $this->form['title'] = (string) $templateState['title'];
        $this->form['description'] = (string) $templateState['description'];
        $this->form['program_starts_at'] = (string) $templateState['program_starts_at'];
        $this->form['program_ends_at'] = (string) $templateState['program_ends_at'];
        $this->activeStep = 2;
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
        $programStartsAt = Carbon::parse((string) $validated['form']['program_starts_at'], $timezone)->utc();
        $programEndsAt = Carbon::parse((string) $validated['form']['program_ends_at'], $timezone)->utc();

        $this->ensureOrganizerIsMemberOwned($user, $organizerType, $organizerId);

        $locationInstitutionId = $this->resolveLocationInstitutionId($user, $organizerType, $organizerId, $validated['form']['location_institution_id'] ?? null);

        if ($programEndsAt->lessThanOrEqualTo($programStartsAt)) {
            $this->addError('form.program_ends_at', __('The program end must be after the program start.'));

            return null;
        }

        try {
            $parentEvent = DB::transaction(function () use ($user, $validated, $timezone, $organizerType, $organizerId, $locationInstitutionId, $programStartsAt, $programEndsAt): Event {
                $parentEvent = Event::query()->create([
                    'user_id' => $user->id,
                    'submitter_id' => $user->id,
                    'parent_event_id' => null,
                    'event_structure' => EventStructure::ParentProgram->value,
                    'title' => (string) $validated['form']['title'],
                    'slug' => Str::slug((string) $validated['form']['title']).'-'.Str::lower(Str::random(7)),
                    'description' => (string) ($validated['form']['description'] ?? ''),
                    'starts_at' => $programStartsAt,
                    'ends_at' => $programEndsAt,
                    'timezone' => $timezone,
                    'institution_id' => $locationInstitutionId,
                    'organizer_type' => $this->organizerMorphClass($organizerType),
                    'organizer_id' => $organizerId,
                    'event_type' => [(string) $validated['form']['default_event_type']],
                    'event_format' => (string) $validated['form']['default_event_format'],
                    'visibility' => (string) $validated['form']['visibility'],
                    'schedule_kind' => ScheduleKind::Single->value,
                    'schedule_state' => ScheduleState::Active->value,
                    'status' => 'draft',
                    'is_active' => true,
                ]);

                $parentEvent->settings()->create([
                    'registration_required' => (bool) $validated['form']['registration_required'],
                    'registration_mode' => (string) $validated['form']['registration_mode'],
                ]);

                return $parentEvent;
            });
        } catch (Throwable $throwable) {
            report($throwable);

            $this->addError('form.title', __('The advanced event could not be created. Please try again.'));

            return null;
        }

        return redirect()->route('submit-event.create', ['parent' => $parentEvent->id]);
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
            'form.program_starts_at' => ['required', 'date'],
            'form.program_ends_at' => ['required', 'date'],
            'form.organizer_type' => ['required', Rule::in(['institution', 'speaker'])],
            'form.organizer_id' => ['required', 'string'],
            'form.location_institution_id' => ['nullable', 'string'],
            'form.default_event_type' => ['required', Rule::in(array_column(EventType::cases(), 'value'))],
            'form.default_event_format' => ['required', Rule::in(array_column(EventFormat::cases(), 'value'))],
            'form.visibility' => ['required', Rule::in(array_column(EventVisibility::cases(), 'value'))],
            'form.registration_required' => ['required', 'boolean'],
            'form.registration_mode' => ['required', Rule::in(array_column(RegistrationMode::cases(), 'value'))],
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

    protected function organizerMorphClass(string $organizerType): string
    {
        return $organizerType === 'institution' ? Institution::class : Speaker::class;
    }

    /**
     * @return array<int, array{number: int, title: string, description: string}>
     */
    protected function stepOptions(): array
    {
        return [
            1 => ['number' => 1, 'title' => __('Program Identity'), 'description' => __('Name the umbrella program and ownership')],
            2 => ['number' => 2, 'title' => __('Program Defaults'), 'description' => __('Set timeframe, visibility, and registration defaults')],
            3 => ['number' => 3, 'title' => __('Review & Continue'), 'description' => __('Create the parent first, then add child events individually')],
        ];
    }

    /**
     * @return array<int, array{key: string, title: string, description: string, eyebrow: string}>
     */
    protected function templateOptions(): array
    {
        return [
            ['key' => 'weekly_series', 'title' => __('Weekly Series'), 'description' => __('Use one parent program for a weekly chain of child event submissions.'), 'eyebrow' => __('Series')],
            ['key' => 'weekend_intensive', 'title' => __('Weekend Intensive'), 'description' => __('Create one parent, then submit each session separately under it.'), 'eyebrow' => __('Focused')],
            ['key' => 'ramadan_program', 'title' => __('Ramadan Program'), 'description' => __('Set up the parent first, then add nightly child events one by one.'), 'eyebrow' => __('Seasonal')],
        ];
    }

    public function render(): View
    {
        return view('livewire.pages.dashboard.events.create-advanced', [
            'institutionOptions' => $this->memberInstitutions()->pluck('name', 'id')->all(),
            'speakerOptions' => $this->memberSpeakers()->pluck('name', 'id')->all(),
            'eventTypeOptions' => collect(EventType::cases())->mapWithKeys(fn (EventType $type): array => [$type->value => $type->getLabel()])->all(),
            'eventFormatOptions' => collect(EventFormat::cases())->mapWithKeys(fn (EventFormat $format): array => [$format->value => $format->label()])->all(),
            'visibilityOptions' => collect(EventVisibility::cases())->mapWithKeys(fn (EventVisibility $visibility): array => [$visibility->value => $visibility->getLabel()])->all(),
            'stepOptions' => $this->stepOptions(),
            'templateOptions' => $this->templateOptions(),
        ]);
    }
}
