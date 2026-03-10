<?php

namespace App\Enums;

enum NotificationTrigger: string
{
    case FollowedSpeakerEvent = 'followed_speaker_event';
    case FollowedInstitutionEvent = 'followed_institution_event';
    case FollowedSeriesEvent = 'followed_series_event';
    case FollowedReferenceEvent = 'followed_reference_event';
    case SavedSearchMatch = 'saved_search_match';
    case EventApproved = 'event_approved';
    case EventCancelled = 'event_cancelled';
    case EventScheduleChanged = 'event_schedule_changed';
    case EventVenueChanged = 'event_venue_changed';
    case EventSessionChanged = 'event_session_changed';
    case Reminder24Hours = 'reminder_24_hours';
    case Reminder2Hours = 'reminder_2_hours';
    case CheckinOpen = 'checkin_open';
    case RegistrationConfirmed = 'registration_confirmed';
    case RegistrationEventChanged = 'registration_event_changed';
    case CheckinConfirmed = 'checkin_confirmed';
    case SubmissionReceived = 'submission_received';
    case SubmissionApproved = 'submission_approved';
    case SubmissionRejected = 'submission_rejected';
    case SubmissionNeedsChanges = 'submission_needs_changes';
    case SubmissionCancelled = 'submission_cancelled';
    case SubmissionRemoderated = 'submission_remoderated';
}
