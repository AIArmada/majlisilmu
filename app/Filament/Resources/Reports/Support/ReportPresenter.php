<?php

namespace App\Filament\Resources\Reports\Support;

use App\Filament\Resources\DonationChannels\DonationChannelResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Institutions\InstitutionResource;
use App\Filament\Resources\References\ReferenceResource;
use App\Filament\Resources\Speakers\SpeakerResource;
use App\Models\DonationChannel;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Report;
use App\Models\Speaker;

class ReportPresenter
{
    public static function entityTitle(Report $report): string
    {
        $entity = $report->entity;

        return match (true) {
            $entity instanceof Event => $entity->title,
            $entity instanceof Institution => $entity->name,
            $entity instanceof Speaker => $entity->formatted_name,
            $entity instanceof Reference => $entity->title,
            $entity instanceof DonationChannel => $entity->label !== '' ? $entity->label : $entity->account_name,
            default => (string) $report->entity_id,
        };
    }

    public static function entityAdminUrl(Report $report): ?string
    {
        $entity = $report->entity;

        return match (true) {
            $entity instanceof Event => EventResource::getUrl('view', ['record' => $entity], panel: 'admin'),
            $entity instanceof Institution => InstitutionResource::getUrl('edit', ['record' => $entity], panel: 'admin'),
            $entity instanceof Speaker => SpeakerResource::getUrl('edit', ['record' => $entity], panel: 'admin'),
            $entity instanceof Reference => ReferenceResource::getUrl('edit', ['record' => $entity], panel: 'admin'),
            $entity instanceof DonationChannel => DonationChannelResource::getUrl('edit', ['record' => $entity], panel: 'admin'),
            default => null,
        };
    }
}
