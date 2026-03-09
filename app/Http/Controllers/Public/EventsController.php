<?php

namespace App\Http\Controllers\Public;

use App\Enums\RegistrationMode;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\EventSettings;
use App\Models\Registration;
use App\Models\User;
use App\Services\CalendarService;
use App\Services\DawahShare\DawahShareService;
use App\Services\Notifications\EventNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventsController extends Controller
{
    public function __construct(
        protected CalendarService $calendarService,
        protected DawahShareService $dawahShareService,
        protected EventNotificationService $eventNotificationService,
    ) {}

    /**
     * Download ICS calendar file for an event.
     */
    public function calendar(Event $event): Response
    {
        if ((! in_array((string) $event->status, Event::ENGAGEABLE_STATUSES, true))
            || $event->visibility !== \App\Enums\EventVisibility::Public) {
            abort(404);
        }

        $icsContent = $this->calendarService->generateIcs($event);
        $filename = \Illuminate\Support\Str::slug($event->title).'.ics';

        return response($icsContent)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function register(Request $request, Event $event): RedirectResponse
    {
        // Eager load settings
        $event->load('settings');

        // Validate event is eligible for registration (per B4b)
        if (! in_array((string) $event->status, ['approved', 'pending'], true)) {
            return back()->withErrors(['registration' => 'This event is not available for registration.']);
        }

        $settings = $event->settings;

        if (! $settings instanceof EventSettings || ! $settings->registration_required) {
            return back()->withErrors(['registration' => 'This event does not require registration.']);
        }

        if ($settings->registration_opens_at instanceof Carbon && $settings->registration_opens_at->isFuture()) {
            return back()->withErrors(['registration' => 'Registration has not opened yet.']);
        }

        if ($settings->registration_closes_at instanceof Carbon && $settings->registration_closes_at->isPast()) {
            return back()->withErrors(['registration' => 'Registration has closed.']);
        }

        $registrationMode = $this->resolveRegistrationMode($settings);

        // Validate request
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'event_session_id' => 'nullable|uuid',
        ]);

        // Require at least email or phone for guest
        if (! auth()->check() && empty($validated['email']) && empty($validated['phone'])) {
            return back()->withErrors(['contact' => 'Please provide either email or phone number.']);
        }

        if ($registrationMode === RegistrationMode::Session && empty($validated['event_session_id'])) {
            return back()->withErrors(['event_session_id' => 'Please choose a session to register.']);
        }

        $createdRegistrationId = null;

        try {
            DB::transaction(function () use ($event, $validated, &$createdRegistrationId): void {
                /** @var Event $lockedEvent */
                $lockedEvent = Event::query()
                    ->whereKey($event->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedEvent->load('settings');
                $lockedEventSettings = $lockedEvent->settings;
                $mode = $this->resolveRegistrationMode($lockedEventSettings);

                $selectedSession = null;
                $capacity = $lockedEventSettings?->capacity;

                if ($mode === RegistrationMode::Session) {
                    $selectedSessionId = $validated['event_session_id'] ?? null;

                    if (! is_string($selectedSessionId) || $selectedSessionId === '') {
                        throw ValidationException::withMessages(['event_session_id' => 'Please choose a session to register.']);
                    }

                    /** @var EventSession|null $selectedSession */
                    $selectedSession = EventSession::query()
                        ->where('event_id', $lockedEvent->id)
                        ->whereKey($selectedSessionId)
                        ->lockForUpdate()
                        ->first();

                    if (! $selectedSession instanceof EventSession || $selectedSession->status !== SessionStatus::Scheduled) {
                        throw ValidationException::withMessages(['event_session_id' => 'Selected session is not available.']);
                    }

                    if ($selectedSession->starts_at instanceof Carbon && $selectedSession->starts_at->isPast()) {
                        throw ValidationException::withMessages(['event_session_id' => 'Selected session has already started.']);
                    }

                    if (is_int($selectedSession->capacity)) {
                        $capacity = $selectedSession->capacity;
                    }
                }

                $activeRegistrationsCount = Registration::query()
                    ->where('event_id', $lockedEvent->id)
                    ->when($mode === RegistrationMode::Session, fn (Builder $query): Builder => $query->where('event_session_id', $validated['event_session_id'] ?? null))
                    ->where('status', '!=', 'cancelled')
                    ->count();

                if ($lockedEventSettings instanceof EventSettings
                    && is_int($capacity)
                    && $activeRegistrationsCount >= $capacity) {
                    throw ValidationException::withMessages(['registration' => 'This event is full.']);
                }

                $existingRegistrationQuery = Registration::query()
                    ->where('event_id', $lockedEvent->id)
                    ->when($mode === RegistrationMode::Session, fn (Builder $query): Builder => $query->where('event_session_id', $validated['event_session_id'] ?? null))
                    ->where('status', '!=', 'cancelled');

                if (auth()->check()) {
                    $existingRegistration = (clone $existingRegistrationQuery)
                        ->where('user_id', auth()->id())
                        ->exists();
                } else {
                    $existingRegistration = (clone $existingRegistrationQuery)
                        ->where(function (Builder $query) use ($validated): void {
                            $hasContactConstraint = false;

                            if (! empty($validated['email'])) {
                                $query->where('email', $validated['email']);
                                $hasContactConstraint = true;
                            }

                            if (! empty($validated['phone'])) {
                                if ($hasContactConstraint) {
                                    $query->orWhere('phone', $validated['phone']);
                                } else {
                                    $query->where('phone', $validated['phone']);
                                }
                            }
                        })
                        ->exists();
                }

                if ($existingRegistration) {
                    throw ValidationException::withMessages(['registration' => 'You are already registered for this event.']);
                }

                $registration = Registration::create([
                    'event_id' => $lockedEvent->id,
                    'event_session_id' => $mode === RegistrationMode::Session ? ($validated['event_session_id'] ?? null) : null,
                    'user_id' => auth()->id(),
                    'name' => $validated['name'],
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'status' => 'registered',
                ]);

                $createdRegistrationId = $registration->id;

                $lockedEvent->update([
                    'registrations_count' => Registration::query()
                        ->where('event_id', $lockedEvent->id)
                        ->where('status', '!=', 'cancelled')
                        ->count(),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if ($this->isRegistrationDuplicateException($exception)) {
                return back()->withErrors(['registration' => 'You are already registered for this event.']);
            }

            throw $exception;
        }

        if (auth()->check()) {
            $registration = filled($createdRegistrationId)
                ? Registration::query()->find($createdRegistrationId)
                : null;

            if ($registration instanceof Registration) {
                /** @var User|null $actor */
                $actor = auth()->user();

                $this->dawahShareService->recordOutcome(
                    type: \App\Enums\DawahShareOutcomeType::EventRegistration,
                    outcomeKey: 'event_registration:registration:'.$registration->id,
                    subject: $event,
                    actor: $actor,
                    request: $request,
                    metadata: [
                        'registration_id' => $registration->id,
                        'event_session_id' => $registration->event_session_id,
                        'guest' => false,
                    ],
                );

                $this->eventNotificationService->notifyRegistrationConfirmed($registration);
            }

            return back()->with('success', 'You have been registered for this event!');
        }

        $guestRegistration = filled($createdRegistrationId)
            ? Registration::query()->find($createdRegistrationId)
            : null;

        if ($guestRegistration instanceof Registration) {
            $this->dawahShareService->recordOutcome(
                type: \App\Enums\DawahShareOutcomeType::EventRegistration,
                outcomeKey: 'event_registration:registration:'.$guestRegistration->id,
                subject: $event,
                actor: null,
                request: $request,
                metadata: [
                    'registration_id' => $guestRegistration->id,
                    'event_session_id' => $guestRegistration->event_session_id,
                    'guest' => true,
                ],
            );
        }

        return back()->with('success', 'You have been registered for this event!');
    }

    private function isRegistrationDuplicateException(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'registrations_event_id_email_unique')) {
            return true;
        }

        return str_contains($message, 'registrations')
            && str_contains($message, 'unique');
    }

    private function resolveRegistrationMode(?EventSettings $settings): RegistrationMode
    {
        $rawMode = $settings?->registration_mode;

        if ($rawMode instanceof RegistrationMode) {
            return $rawMode;
        }

        if (is_string($rawMode)) {
            return RegistrationMode::tryFrom($rawMode) ?? RegistrationMode::Event;
        }

        return RegistrationMode::Event;
    }
}
