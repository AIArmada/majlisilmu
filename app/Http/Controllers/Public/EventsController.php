<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSettings;
use App\Models\Registration;
use App\Services\CalendarService;
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
        protected CalendarService $calendarService
    ) {}

    /**
     * Download ICS calendar file for an event.
     */
    public function calendar(Event $event): Response
    {
        if ((! in_array((string) $event->status, ['approved', 'pending'], true))
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

        // Validate request
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        // Require at least email or phone for guest
        if (! auth()->check() && empty($validated['email']) && empty($validated['phone'])) {
            return back()->withErrors(['contact' => 'Please provide either email or phone number.']);
        }

        try {
            DB::transaction(function () use ($event, $validated): void {
                /** @var Event $lockedEvent */
                $lockedEvent = Event::query()
                    ->whereKey($event->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedEvent->load('settings');
                $lockedEventSettings = $lockedEvent->settings;

                $activeRegistrationsCount = Registration::query()
                    ->where('event_id', $lockedEvent->id)
                    ->where('status', '!=', 'cancelled')
                    ->count();

                if ($lockedEventSettings instanceof EventSettings
                    && is_int($lockedEventSettings->capacity)
                    && $activeRegistrationsCount >= $lockedEventSettings->capacity) {
                    throw ValidationException::withMessages(['registration' => 'This event is full.']);
                }

                $existingRegistration = Registration::query()
                    ->where('event_id', $lockedEvent->id)
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

                            $hasContactConstraint = true;
                        }

                        if (! $hasContactConstraint && auth()->check()) {
                            $query->where('user_id', auth()->id());
                        }
                    })
                    ->where('status', '!=', 'cancelled')
                    ->exists();

                if ($existingRegistration) {
                    throw ValidationException::withMessages(['registration' => 'You are already registered for this event.']);
                }

                Registration::create([
                    'event_id' => $lockedEvent->id,
                    'user_id' => auth()->id(),
                    'name' => $validated['name'],
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'status' => 'registered',
                ]);

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
}
