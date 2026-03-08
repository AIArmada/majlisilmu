<?php

use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Models\Event;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

it('shows the registrations relation manager for registration-required events', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('super_admin');

    $event = Event::factory()
        ->has(\App\Models\EventSettings::factory()->state([
            'registration_required' => true,
        ]), 'settings')
        ->create();

    $component = Livewire::actingAs($administrator)
        ->test(EditEvent::class, ['record' => $event->id]);

    $relationManagers = $component->instance()->getRelationManagers();

    expect($relationManagers)->toContain(RegistrationsRelationManager::class);
});

it('hides the registrations relation manager for events that do not require registration', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('super_admin');

    $event = Event::factory()
        ->has(\App\Models\EventSettings::factory()->state([
            'registration_required' => false,
        ]), 'settings')
        ->create();

    $component = Livewire::actingAs($administrator)
        ->test(EditEvent::class, ['record' => $event->id]);

    $relationManagers = $component->instance()->getRelationManagers();

    expect($relationManagers)->not->toContain(RegistrationsRelationManager::class);
});
