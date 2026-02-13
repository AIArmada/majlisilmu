<?php

namespace App\Livewire\Pages\Dashboard\Events;

use App\Enums\SessionStatus;
use App\Models\Event;
use App\Models\EventSession;
use App\Services\EventScheduleGeneratorService;
use App\Services\EventScheduleProjectorService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Manage Event Schedule')]
class Schedule extends Component
{
    public Event $event;

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $editSessions = [];

    /**
     * @var array<string, mixed>
     */
    public array $newSession = [
        'starts_at' => '',
        'ends_at' => '',
        'status' => 'scheduled',
        'capacity' => null,
    ];

    public function mount(Event $event): void
    {
        abort_unless(auth()->check(), 403);
        abort_unless(auth()->user()?->can('update', $event), 403);

        $this->event = $event->load(['sessions', 'recurrenceRules', 'settings']);
        $this->hydrateEditSessions();

        if ($this->newSession['starts_at'] === '') {
            $this->newSession['starts_at'] = now($this->event->timezone ?: 'Asia/Kuala_Lumpur')->addDay()->setTime(20, 0)->format('Y-m-d\TH:i');
            $this->newSession['ends_at'] = now($this->event->timezone ?: 'Asia/Kuala_Lumpur')->addDay()->setTime(22, 0)->format('Y-m-d\TH:i');
        }
    }

    public function addSession(EventScheduleGeneratorService $generator): void
    {
        $validated = $this->validate([
            'newSession.starts_at' => ['required', 'date'],
            'newSession.ends_at' => ['nullable', 'date', 'after:newSession.starts_at'],
            'newSession.status' => ['required', 'in:scheduled,paused,cancelled'],
            'newSession.capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        $timezone = $this->event->timezone ?: 'Asia/Kuala_Lumpur';

        $generator->upsertManualSession($this->event, [
            'starts_at' => Carbon::parse($validated['newSession']['starts_at'], $timezone),
            'ends_at' => filled($validated['newSession']['ends_at']) ? Carbon::parse($validated['newSession']['ends_at'], $timezone) : null,
            'status' => $validated['newSession']['status'],
            'capacity' => $validated['newSession']['capacity'] ?? null,
            'timezone' => $timezone,
            'timing_mode' => 'absolute',
        ]);

        $this->refreshEvent();
    }

    public function saveSession(string $sessionId, EventScheduleGeneratorService $generator): void
    {
        $sessionState = $this->editSessions[$sessionId] ?? null;

        if (! is_array($sessionState)) {
            return;
        }

        $validatorData = [
            'starts_at' => $sessionState['starts_at'] ?? null,
            'ends_at' => $sessionState['ends_at'] ?? null,
            'status' => $sessionState['status'] ?? null,
            'capacity' => $sessionState['capacity'] ?? null,
        ];

        $validated = validator($validatorData, [
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['required', 'in:scheduled,paused,cancelled'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $session = EventSession::query()->where('event_id', $this->event->id)->findOrFail($sessionId);
        $timezone = $this->event->timezone ?: 'Asia/Kuala_Lumpur';

        $generator->upsertManualSession($this->event, [
            'starts_at' => Carbon::parse($validated['starts_at'], $timezone),
            'ends_at' => filled($validated['ends_at']) ? Carbon::parse($validated['ends_at'], $timezone) : null,
            'status' => $validated['status'],
            'capacity' => $validated['capacity'] ?? null,
            'timezone' => $timezone,
            'timing_mode' => 'absolute',
        ], $session);

        $this->refreshEvent();
    }

    public function cancelSession(string $sessionId, EventScheduleGeneratorService $generator): void
    {
        $session = EventSession::query()->where('event_id', $this->event->id)->findOrFail($sessionId);
        $generator->cancelSession($session);
        $this->refreshEvent();
    }

    public function pauseSeries(EventScheduleGeneratorService $generator): void
    {
        $generator->pauseSeries($this->event);
        $this->refreshEvent();
    }

    public function resumeSeries(EventScheduleGeneratorService $generator): void
    {
        $generator->resumeSeries($this->event);
        $this->refreshEvent();
    }

    public function cancelSeries(EventScheduleGeneratorService $generator): void
    {
        $generator->cancelSeries($this->event);
        $this->refreshEvent();
    }

    public function regenerateRecurring(EventScheduleGeneratorService $generator, EventScheduleProjectorService $projector): void
    {
        $this->event->load('recurrenceRules');

        foreach ($this->event->recurrenceRules as $rule) {
            if ($rule->isActive()) {
                $generator->syncRecurringSessions($this->event, $rule, false);
            }
        }

        $projector->project($this->event->fresh());
        $this->refreshEvent();
    }

    protected function refreshEvent(): void
    {
        $this->event = $this->event->fresh(['sessions', 'recurrenceRules', 'settings']);
        $this->hydrateEditSessions();
    }

    protected function hydrateEditSessions(): void
    {
        $this->editSessions = [];

        foreach ($this->event->sessions as $session) {
            $startsAt = $session->starts_at instanceof Carbon
                ? $session->starts_at->format('Y-m-d\TH:i')
                : (is_string($session->starts_at) ? Carbon::parse($session->starts_at)->format('Y-m-d\TH:i') : '');

            $endsAt = $session->ends_at instanceof Carbon
                ? $session->ends_at->format('Y-m-d\TH:i')
                : (is_string($session->ends_at) ? Carbon::parse($session->ends_at)->format('Y-m-d\TH:i') : '');

            $this->editSessions[$session->id] = [
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => ($session->status instanceof SessionStatus ? $session->status->value : (string) $session->status) ?: SessionStatus::Scheduled->value,
                'capacity' => $session->capacity,
            ];
        }
    }

    public function render(): View
    {
        return view('livewire.pages.dashboard.events.schedule');
    }
}
