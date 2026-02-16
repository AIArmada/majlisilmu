<?php

use App\Filament\Resources\Speakers\SpeakerResource;
use App\Models\Speaker;
use App\Models\User;

it('loads speaker edit page when speaker has social media row', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $administrator = User::factory()->create();
    $administrator->assignRole('super_admin');

    $speaker = Speaker::factory()->create();

    $speaker->socialMedia()->create([
        'platform' => 'facebook',
        'username' => 'atiqah',
        'url' => 'https://www.facebook.com/atiqah',
    ]);

    $this->actingAs($administrator)
        ->get(SpeakerResource::getUrl('edit', ['record' => $speaker]))
        ->assertSuccessful();
});
