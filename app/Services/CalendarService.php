<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Institution;
use App\Models\Venue;
use Illuminate\Support\Carbon;

class CalendarService
{
    /**
     * Generate Google Calendar URL for an event.
     */
    public function googleCalendarUrl(Event $event): string
    {
        $params = [
            'action' => 'TEMPLATE',
            'text' => $event->title,
            'dates' => $this->formatGoogleDates($event),
            'details' => $this->formatDescription($event),
            'location' => $this->formatLocation($event),
        ];

        return 'https://calendar.google.com/calendar/render?'.http_build_query($params);
    }

    /**
     * Generate Outlook/Office 365 Calendar URL for an event.
     */
    public function outlookCalendarUrl(Event $event): string
    {
        $params = [
            'path' => '/calendar/0/deeplink/compose',
            'rru' => 'addevent',
            'subject' => $event->title,
            'body' => $event->description_text,
            'location' => $this->formatLocation($event),
            'startdt' => $event->starts_at?->toIso8601String(),
            'enddt' => $this->defaultEndAt($event)?->toIso8601String(),
        ];

        return 'https://outlook.live.com/calendar/0/deeplink/compose?'.http_build_query($params);
    }

    /**
     * Generate Office 365 Calendar URL for an event.
     */
    public function office365CalendarUrl(Event $event): string
    {
        $params = [
            'path' => '/calendar/action/compose',
            'rru' => 'addevent',
            'subject' => $event->title,
            'body' => $event->description_text,
            'location' => $this->formatLocation($event),
            'startdt' => $event->starts_at?->toIso8601String(),
            'enddt' => $this->defaultEndAt($event)?->toIso8601String(),
        ];

        return 'https://outlook.office.com/calendar/0/deeplink/compose?'.http_build_query($params);
    }

    /**
     * Generate Yahoo Calendar URL for an event.
     */
    public function yahooCalendarUrl(Event $event): string
    {
        $params = [
            'v' => '60',
            'title' => $event->title,
            'st' => $event->starts_at?->format('Ymd\THis'),
            'et' => $this->defaultEndAt($event)?->format('Ymd\THis'),
            'desc' => $event->description_text,
            'in_loc' => $this->formatLocation($event),
        ];

        return 'https://calendar.yahoo.com/?'.http_build_query($params);
    }

    /**
     * Generate ICS file content for an event.
     */
    public function generateIcs(Event $event): string
    {
        $uid = $event->id.'@'.config('app.url');
        $dtstamp = now()->format('Ymd\THis\Z');
        $dtstart = $event->starts_at?->setTimezone('UTC')->format('Ymd\THis\Z');
        $dtend = $this->defaultEndAt($event)?->setTimezone('UTC')->format('Ymd\THis\Z');
        $summary = $this->escapeIcs($event->title);
        $description = $this->escapeIcs($this->formatDescription($event));
        $location = $this->escapeIcs($this->formatLocation($event));
        $url = route('events.show', $event);

        $organizer = $event->institution instanceof Institution
            ? $event->institution->name
            : config('app.name');

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= 'PRODID:-//'.config('app.name')."//Events//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:{$uid}\r\n";
        $ics .= "DTSTAMP:{$dtstamp}\r\n";
        $ics .= "DTSTART:{$dtstart}\r\n";
        $ics .= "DTEND:{$dtend}\r\n";
        $ics .= "SUMMARY:{$summary}\r\n";
        $ics .= "DESCRIPTION:{$description}\r\n";
        $ics .= "LOCATION:{$location}\r\n";
        $ics .= "URL:{$url}\r\n";
        $ics .= "ORGANIZER;CN={$organizer}:MAILTO:".config('mail.from.address', 'noreply@example.com')."\r\n";
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "TRANSP:OPAQUE\r\n";

        // Add alarm (reminder) 1 hour before
        $ics .= "BEGIN:VALARM\r\n";
        $ics .= "TRIGGER:-PT1H\r\n";
        $ics .= "ACTION:DISPLAY\r\n";
        $ics .= "DESCRIPTION:Reminder: {$summary}\r\n";
        $ics .= "END:VALARM\r\n";

        $ics .= "END:VEVENT\r\n";

        return $ics."END:VCALENDAR\r\n";
    }

    /**
     * Format dates for Google Calendar (YYYYMMDDTHHMMSS/YYYYMMDDTHHMMSS).
     */
    protected function formatGoogleDates(Event $event): string
    {
        $start = $event->starts_at?->setTimezone('UTC')->format('Ymd\THis\Z');
        $end = $this->defaultEndAt($event)?->setTimezone('UTC')->format('Ymd\THis\Z');

        return "{$start}/{$end}";
    }

    /**
     * Format the event description with additional details.
     */
    protected function formatDescription(Event $event): string
    {
        $parts = [];

        if ($event->description_text !== '') {
            $parts[] = $event->description_text;
        }

        // Add prayer-relative timing info
        if ($event->isPrayerRelative() && $event->prayer_display_text) {
            $parts[] = '';
            $parts[] = "Waktu: {$event->prayer_display_text}";
        }

        // Add speakers
        if ($event->speakers->isNotEmpty()) {
            $speakerNames = $event->speakers->pluck('name')->join(', ');
            $parts[] = '';
            $parts[] = "Penceramah: {$speakerNames}";
        }

        // Add link to event page
        $parts[] = '';
        $parts[] = 'Maklumat lanjut: '.route('events.show', $event);

        return implode("\n", $parts);
    }

    private function defaultEndAt(Event $event): ?Carbon
    {
        if ($event->ends_at instanceof Carbon) {
            return $event->ends_at->copy();
        }

        if ($event->starts_at instanceof Carbon) {
            return $event->starts_at->copy()->addHours(2);
        }

        return null;
    }

    /**
     * Format the event location.
     */
    protected function formatLocation(Event $event): string
    {
        $parts = [];
        $venue = $event->venue;
        $institution = $event->institution;

        if ($venue instanceof Venue) {
            $parts[] = $venue->name;

            if ($venue->address_line1 !== '') {
                $parts[] = $venue->address_line1;
            }
        } elseif ($institution instanceof Institution) {
            $parts[] = $institution->name;

            if ($institution->address_line1 !== '') {
                $parts[] = $institution->address_line1;
            }
        }

        return implode(', ', $parts) ?: 'Online';
    }

    /**
     * Escape special characters for ICS format.
     */
    protected function escapeIcs(string $text): string
    {
        $text = str_replace(['\\', "\n", ',', ';'], ['\\\\', '\\n', '\\,', '\\;'], $text);

        // Fold long lines (max 75 chars per line)
        return wordwrap($text, 73, "\r\n ", true);
    }

    /**
     * Get all calendar links for an event.
     *
     * @return array<string, string>
     */
    public function getAllCalendarLinks(Event $event): array
    {
        return [
            'google' => $this->googleCalendarUrl($event),
            'outlook' => $this->outlookCalendarUrl($event),
            'office365' => $this->office365CalendarUrl($event),
            'yahoo' => $this->yahooCalendarUrl($event),
            'ics' => route('events.calendar', $event),
        ];
    }
}
