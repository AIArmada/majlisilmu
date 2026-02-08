<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Services\CalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
        if ((! $event->status?->equals(\App\States\EventStatus\Approved::class)
            && ! $event->status?->equals(\App\States\EventStatus\Pending::class))
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
        if (! $event->status?->equals(\App\States\EventStatus\Approved::class)
            && ! $event->status?->equals(\App\States\EventStatus\Pending::class)) {
            return back()->withErrors(['registration' => 'This event is not available for registration.']);
        }

        if ($event->status?->equals(\App\States\EventStatus\Rejected::class)) {
            return back()->withErrors(['registration' => 'This event has been cancelled.']);
        }

        if (! $event->settings?->registration_required) {
            return back()->withErrors(['registration' => 'This event does not require registration.']);
        }

        // Check registration window
        if ($event->settings?->registration_opens_at && $event->settings->registration_opens_at->isFuture()) {
            return back()->withErrors(['registration' => 'Registration has not opened yet.']);
        }

        if ($event->settings?->registration_closes_at && $event->settings->registration_closes_at->isPast()) {
            return back()->withErrors(['registration' => 'Registration has closed.']);
        }

        // Check capacity
        if ($event->settings?->capacity && $event->registrations_count >= $event->settings->capacity) {
            return back()->withErrors(['registration' => 'This event is full.']);
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

        // Check for duplicate registration
        $existingRegistration = Registration::where('event_id', $event->id)
            ->where(function ($query) use ($validated) {
                if (! empty($validated['email'])) {
                    $query->where('email', $validated['email']);
                }
                if (! empty($validated['phone'])) {
                    $query->orWhere('phone', $validated['phone']);
                }
            })
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($existingRegistration) {
            return back()->withErrors(['registration' => 'You are already registered for this event.']);
        }

        // Create registration
        $registration = Registration::create([
            'event_id' => $event->id,
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'status' => 'registered',
        ]);

        // Increment registrations count
        $event->increment('registrations_count');

        return back()->with('success', 'You have been registered for this event!');
    }
}
