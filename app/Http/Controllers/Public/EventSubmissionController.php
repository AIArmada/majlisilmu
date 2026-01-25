<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSubmission;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EventSubmissionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:160',
            'description' => 'nullable|string|max:5000',
            'starts_at' => 'required|date|after:now',
            'ends_at' => 'nullable|date|after:starts_at',
            'timezone' => 'nullable|string|timezone',
            'institution_id' => 'nullable|uuid|exists:institutions,id',
            'venue_id' => 'nullable|uuid|exists:venues,id',
            'venue_name' => 'required_without:venue_id|nullable|string|max:200',
            'address' => 'nullable|string|max:500',
            'state_id' => 'nullable|uuid|exists:states,id',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'language' => ['nullable', Rule::in(['malay', 'english', 'arabic', 'mixed'])],
            'event_type' => ['nullable', Rule::enum(\App\Enums\EventType::class)],
            'gender_restriction' => ['nullable', Rule::enum(\App\Enums\EventGenderRestriction::class)],
            'age_group' => ['nullable', Rule::enum(\App\Enums\EventAgeGroup::class)],
            'children_allowed' => 'nullable|boolean',
            'speaker_ids' => 'nullable|array',
            'speaker_ids.*' => 'uuid|exists:speakers,id',
            'topic_ids' => 'nullable|array',
            'topic_ids.*' => 'uuid|exists:topics,id',
            'submitter_name' => 'required|string|max:100',
            'submitter_email' => 'nullable|email|max:255',
            'submitter_phone' => 'nullable|string|max:20',
        ]);

        // Require at least email or phone for guest submissions
        if (! auth()->check() && empty($validated['submitter_email']) && empty($validated['submitter_phone'])) {
            return back()->withErrors(['submitter_contact' => 'Please provide either email or phone number.'])->withInput();
        }

        $submitterContact = $validated['submitter_email'] ?? null;
        if ($submitterContact === null || $submitterContact === '') {
            $submitterContact = $validated['submitter_phone'] ?? null;
        }
        $submitterContact = $submitterContact !== '' ? $submitterContact : null;

        // Handle venue creation if needed
        $venueId = $validated['venue_id'] ?? null;
        if (! $venueId && ! empty($validated['venue_name'])) {
            $venue = Venue::create([
                'name' => $validated['venue_name'],
                'address_line1' => $validated['address'] ?? null,
                'state_id' => $validated['state_id'] ?? null,
                'lat' => $validated['lat'] ?? null,
                'lng' => $validated['lng'] ?? null,
            ]);
            $venueId = $venue->id;
        }

        // Create the event
        $event = Event::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']).'-'.Str::random(6),
            'description' => $validated['description'] ?? null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'timezone' => $validated['timezone'] ?? 'Asia/Kuala_Lumpur',
            'institution_id' => $validated['institution_id'] ?? null,
            'venue_id' => $venueId,
            'state_id' => $validated['state_id'] ?? null,
            'language' => $validated['language'] ?? 'malay',
            'event_type' => $validated['event_type'] ?? \App\Enums\EventType::Kuliah,
            'gender_restriction' => $validated['gender_restriction'] ?? \App\Enums\EventGenderRestriction::All,
            'age_group' => $validated['age_group'] ?? \App\Enums\EventAgeGroup::AllAges,
            'children_allowed' => $validated['children_allowed'] ?? true,
            'status' => 'pending',
            'visibility' => 'public',
            'submitter_id' => auth()->id(),
        ]);

        // Attach speakers
        if (! empty($validated['speaker_ids'])) {
            $event->speakers()->attach($validated['speaker_ids']);
        }

        // Attach topics
        if (! empty($validated['topic_ids'])) {
            $event->topics()->attach($validated['topic_ids']);
        }

        // Create submission record for tracking
        EventSubmission::create([
            'event_id' => $event->id,
            'submitter_name' => $validated['submitter_name'],
            'submitter_contact' => $submitterContact,
            'submitted_by' => auth()->id(),
            'source' => 'public',
        ]);

        return redirect()->route('submit-event.success')->with('event_title', $event->title);
    }
}
