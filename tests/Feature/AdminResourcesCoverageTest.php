<?php

use App\Filament\Resources\AiModelPricings\AiModelPricingResource;
use App\Filament\Resources\AiUsageLogs\AiUsageLogResource;
use App\Filament\Resources\DonationChannels\DonationChannelResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Institutions\InstitutionResource;
use App\Filament\Resources\Institutions\RelationManagers\DonationChannelsRelationManager as InstitutionDonationChannelsRelationManager;
use App\Filament\Resources\References\ReferenceResource;
use App\Filament\Resources\References\RelationManagers\EventsRelationManager as ReferenceEventsRelationManager;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\Series\RelationManagers\EventsRelationManager as SeriesEventsRelationManager;
use App\Filament\Resources\Series\SeriesResource;
use App\Filament\Resources\Spaces\SpaceResource;
use App\Filament\Resources\Speakers\RelationManagers\EventsRelationManager as SpeakerEventsRelationManager;
use App\Filament\Resources\Speakers\RelationManagers\FollowersRelationManager as SpeakerFollowersRelationManager;
use App\Filament\Resources\Speakers\SpeakerResource;
use App\Filament\Resources\Tags\TagResource;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\User;

it('allows super admin to access all core admin resource index pages', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $administrator = User::factory()->create();
    $administrator->assignRole('super_admin');

    $resources = [
        EventResource::class,
        InstitutionResource::class,
        SpeakerResource::class,
        VenueResource::class,
        SeriesResource::class,
        ReferenceResource::class,
        DonationChannelResource::class,
        TagResource::class,
        ReportResource::class,
        SpaceResource::class,
        AiUsageLogResource::class,
        AiModelPricingResource::class,
    ];

    foreach ($resources as $resource) {
        $this->actingAs($administrator)
            ->get($resource::getUrl('index'))
            ->assertSuccessful();
    }
});

it('registers expected relation managers on core admin resources', function () {
    expect(SeriesResource::getRelations())->toContain(SeriesEventsRelationManager::class);
    expect(SpeakerResource::getRelations())->toContain(SpeakerEventsRelationManager::class);
    expect(SpeakerResource::getRelations())->toContain(SpeakerFollowersRelationManager::class);
    expect(ReferenceResource::getRelations())->toContain(ReferenceEventsRelationManager::class);
    expect(InstitutionResource::getRelations())->toContain(InstitutionDonationChannelsRelationManager::class);
});
