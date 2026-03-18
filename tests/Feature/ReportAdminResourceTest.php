<?php

use App\Filament\Resources\References\ReferenceResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Reference;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
});

it('shows the reported subject title and admin link on the reports index', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('super_admin');

    $reference = Reference::factory()->create([
        'title' => 'Rujukan Untuk Disemak',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $reference->reports()->create([
        'category' => 'fake_reference',
        'status' => 'open',
    ]);

    $this->actingAs($administrator)
        ->get(ReportResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee('Rujukan Untuk Disemak')
        ->assertSee(ReferenceResource::getUrl('edit', ['record' => $reference], panel: 'admin'), false);
});
