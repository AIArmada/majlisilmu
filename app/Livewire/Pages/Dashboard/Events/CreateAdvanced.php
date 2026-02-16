<?php

namespace App\Livewire\Pages\Dashboard\Events;

use App\Enums\EventFormat;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\RecurrenceFrequency;
use App\Enums\RegistrationMode;
use App\Enums\ScheduleKind;
use App\Enums\ScheduleState;
use App\Models\Event;
use App\Models\EventRecurrenceRule;
use App\Services\EventScheduleGeneratorService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

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

        $this->form = [
            'title' => '',
            'description' => '',
            'schedule_kind' => ScheduleKind::Single->value,
            'timezone' => 'Asia/Kuala_Lumpur',
            'event_format' => EventFormat::Physical->value,
            'visibility' => EventVisibility::Public->value,
            'registration_required' => true,
            'registration_mode' => RegistrationMode::Event->value,
            'sessions' => [
                [
                    'starts_at' => now()->addDay()->setTime(20, 0)->format('Y-m-d\TH:i'),
                    'ends_at' => now()->addDay()->setTime(22, 0)->format('Y-m-d\TH:i'),
                    'status' => 'scheduled',
                    'capacity' => null,
                ],
            ],
            'recurrence' => [
                'frequency' => RecurrenceFrequency::Weekly->value,
                'interval' => 1,
                'by_weekdays' => [5],
                'by_month_day' => null,
                'start_date' => now()->addDay()->toDateString(),
                'until_date' => now()->addMonths(2)->toDateString(),
                'occurrence_count' => null,
                'timing_mode' => 'absolute',
                'starts_time' => '20:00',
                'ends_time' => '22:00',
                'prayer_reference' => null,
                'prayer_offset' => null,
                'prayer_display_text' => null,
            ],
        ];
    }

    public function addSession(): void
    {
        $this->form['sessions'][] = [
            'starts_at' => now()->addDay()->setTime(20, 0)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->setTime(22, 0)->format('Y-m-d\TH:i'),
            'status' => 'scheduled',
            'capacity' => null,
        ];
    }

    public function removeSession(int $index): void
    {
        unset($this->form['sessions'][$index]);
        $this->form['sessions'] = array_values($this->form['sessions']);
    }

    public function submit(EventScheduleGeneratorService $generator): mixed
    {
        $validated = $this->validate($this->rules(), $this->messages());

        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        abort_unless($user !== null, 403);

        $timezone = (string) ($validated['form']['timezone'] ?? 'Asia/Kuala_Lumpur');

        $event = Event::query()->create([
            'user_id' => $user->id,
            'submitter_id' => $user->id,
            'title' => (string) $validated['form']['title'],
            'slug' => Str::slug((string) $validated['form']['title']).'-'.Str::lower(Str::random(7)),
            'description' => (string) ($validated['form']['description'] ?? ''),
            'starts_at' => now($timezone),
            'ends_at' => now($timezone)->addHours(2),
            'timezone' => $timezone,
            'event_type' => [EventType::KuliahCeramah],
            'event_format' => (string) $validated['form']['event_format'],
            'visibility' => (string) $validated['form']['visibility'],
            'schedule_kind' => (string) $validated['form']['schedule_kind'],
            'schedule_state' => ScheduleState::Active->value,
            'is_active' => true,
        ]);

        $event->settings()->updateOrCreate(
            ['event_id' => $event->id],
            [
                'registration_required' => (bool) ($validated['form']['registration_required'] ?? true),
                'registration_mode' => (string) ($validated['form']['registration_mode'] ?? RegistrationMode::Event->value),
            ],
        );

        $scheduleKind = (string) $validated['form']['schedule_kind'];

        if ($scheduleKind === ScheduleKind::Recurring->value) {
            $recurrence = $validated['form']['recurrence'];

            EventRecurrenceRule::query()->create([
                'event_id' => $event->id,
                'frequency' => $recurrence['frequency'],
                'interval' => (int) $recurrence['interval'],
                'by_weekdays' => $recurrence['by_weekdays'] ?? null,
                'by_month_day' => $recurrence['by_month_day'] ?? null,
                'start_date' => $recurrence['start_date'],
                'until_date' => $recurrence['until_date'] ?? null,
                'occurrence_count' => $recurrence['occurrence_count'] ?? null,
                'starts_time' => $recurrence['starts_time'] ? Carbon::parse($recurrence['starts_time'])->format('H:i:s') : null,
                'ends_time' => $recurrence['ends_time'] ? Carbon::parse($recurrence['ends_time'])->format('H:i:s') : null,
                'timezone' => $timezone,
                'timing_mode' => $recurrence['timing_mode'],
                'prayer_reference' => $recurrence['prayer_reference'] ?? null,
                'prayer_offset' => $recurrence['prayer_offset'] ?? null,
                'prayer_display_text' => $recurrence['prayer_display_text'] ?? null,
                'status' => ScheduleState::Active->value,
            ]);
        } else {
            $sessions = $validated['form']['sessions'] ?? [];

            if ($scheduleKind === ScheduleKind::Single->value && isset($sessions[0])) {
                $sessions = [$sessions[0]];
            }

            foreach ($sessions as $sessionData) {
                $generator->upsertManualSession($event, [
                    'starts_at' => Carbon::parse($sessionData['starts_at'], $timezone),
                    'ends_at' => filled($sessionData['ends_at'] ?? null) ? Carbon::parse($sessionData['ends_at'], $timezone) : null,
                    'timezone' => $timezone,
                    'status' => $sessionData['status'] ?? 'scheduled',
                    'capacity' => $sessionData['capacity'] ?: null,
                    'timing_mode' => 'absolute',
                ]);
            }
        }

        return redirect()->route('dashboard.events.schedule', $event);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [
            'form.title' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string'],
            'form.schedule_kind' => ['required', Rule::in(array_column(ScheduleKind::cases(), 'value'))],
            'form.timezone' => ['required', 'string', 'max:64'],
            'form.event_format' => ['required', Rule::in(array_column(EventFormat::cases(), 'value'))],
            'form.visibility' => ['required', Rule::in(array_column(EventVisibility::cases(), 'value'))],
            'form.registration_required' => ['required', 'boolean'],
            'form.registration_mode' => ['required', Rule::in(array_column(RegistrationMode::cases(), 'value'))],
            'form.sessions' => ['array'],
            'form.sessions.*.starts_at' => ['required', 'date'],
            'form.sessions.*.ends_at' => ['nullable', 'date', 'after:form.sessions.*.starts_at'],
            'form.sessions.*.status' => ['nullable', 'in:scheduled,paused,cancelled'],
            'form.sessions.*.capacity' => ['nullable', 'integer', 'min:1'],
            'form.recurrence.frequency' => ['required', Rule::in(array_column(RecurrenceFrequency::cases(), 'value'))],
            'form.recurrence.interval' => ['required', 'integer', 'min:1'],
            'form.recurrence.by_weekdays' => ['nullable', 'array'],
            'form.recurrence.by_weekdays.*' => ['integer', 'between:0,6'],
            'form.recurrence.by_month_day' => ['nullable', 'integer', 'between:1,31'],
            'form.recurrence.start_date' => ['required', 'date'],
            'form.recurrence.until_date' => ['nullable', 'date'],
            'form.recurrence.occurrence_count' => ['nullable', 'integer', 'min:1'],
            'form.recurrence.timing_mode' => ['required', Rule::in(['absolute', 'prayer_relative'])],
            'form.recurrence.starts_time' => ['nullable', 'date_format:H:i'],
            'form.recurrence.ends_time' => ['nullable', 'date_format:H:i'],
            'form.recurrence.prayer_reference' => ['nullable', 'string'],
            'form.recurrence.prayer_offset' => ['nullable', 'string'],
            'form.recurrence.prayer_display_text' => ['nullable', 'string', 'max:255'],
        ];

        if (($this->form['schedule_kind'] ?? ScheduleKind::Single->value) !== ScheduleKind::Recurring->value) {
            $rules['form.sessions'] = ['required', 'array', 'min:1'];
        }

        if (($this->form['schedule_kind'] ?? ScheduleKind::Single->value) === ScheduleKind::Recurring->value) {
            $rules['form.recurrence.until_date'][] = 'required_without:form.recurrence.occurrence_count';
            $rules['form.recurrence.occurrence_count'][] = 'required_without:form.recurrence.until_date';
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'form.recurrence.until_date.required_without' => __('Set either an end date or occurrence count.'),
            'form.recurrence.occurrence_count.required_without' => __('Set either an end date or occurrence count.'),
        ];
    }

    public function render(): View
    {
        return view('livewire.pages.dashboard.events.create-advanced');
    }
}
