<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum VenueType: string implements HasLabel
{
    case Hall = 'hall';
    case Auditorium = 'auditorium';
    case Classroom = 'classroom';
    case ConferenceRoom = 'conference_room';
    case MeetingRoom = 'meeting_room';
    case SeminarRoom = 'seminar_room';
    case PrayerHall = 'prayer_hall';
    case Outdoor = 'outdoor';
    case Field = 'field';
    case Foyer = 'foyer';
    case Others = 'others';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hall => __('Hall'),
            self::Auditorium => __('Auditorium'),
            self::Classroom => __('Classroom'),
            self::ConferenceRoom => __('Conference Room'),
            self::MeetingRoom => __('Meeting Room'),
            self::SeminarRoom => __('Seminar Room'),
            self::PrayerHall => __('Prayer Hall'),
            self::Outdoor => __('Outdoor Space'),
            self::Field => __('Field'),
            self::Foyer => __('Foyer'),
            self::Others => __('Others'),
        };
    }
}
