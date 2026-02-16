<?php

use App\Filament\Resources\Speakers\Pages\ListSpeakers;
use App\Models\Speaker;
use App\Models\User;
use Livewire\Livewire;

it('shows follower count on admin speakers list', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $administrator = User::factory()->create();
    $administrator->assignRole('super_admin');

    $speaker = Speaker::factory()->create();

    User::factory()->count(2)->create()->each(fn (User $user) => $user->follow($speaker));

    $this->actingAs($administrator);

    Livewire::test(ListSpeakers::class)
        ->assertSee($speaker->name)
        ->assertSee('2');
});
