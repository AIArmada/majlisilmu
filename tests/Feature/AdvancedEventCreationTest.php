<?php

use App\Filament\Ahli\Resources\Events\EventResource as AhliEventResource;
use App\Livewire\Pages\Dashboard\Events\CreateAdvanced;
use App\Models\Event;
use App\Models\EventSubmission;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('forbids the advanced builder for authenticated users without member entities', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.events.create-advanced'))
        ->assertForbidden();
});

it('creates a parent program draft with child event drafts for institution members', function () {
    $user = User::factory()->create(['name' => 'Aisyah Member']);
    $institution = Institution::factory()->create();

    $institution->members()->syncWithoutDetaching([$user->id]);

    $this->actingAs($user)
        ->get(AhliEventResource::getUrl('index', panel: 'ahli'))
        ->assertSuccessful()
        ->assertSee('Create Advanced Program');

    Livewire::actingAs($user)
        ->test(CreateAdvanced::class)
        ->set('form.title', 'Ramadan Knowledge Series')
        ->set('form.description', 'A month-long umbrella program.')
        ->set('form.organizer_type', 'institution')
        ->set('form.organizer_id', $institution->id)
        ->set('form.default_event_type', 'kuliah_ceramah')
        ->set('form.default_event_format', 'physical')
        ->set('form.children', [
            [
                'title' => 'Night One Tafsir',
                'description' => 'Opening tafsir session.',
                'starts_at' => now()->addDays(2)->setTime(20, 0)->format('Y-m-d\TH:i'),
                'ends_at' => now()->addDays(2)->setTime(22, 0)->format('Y-m-d\TH:i'),
                'event_type' => null,
                'event_format' => null,
            ],
            [
                'title' => 'Night Two Q&A',
                'description' => 'Question and answer follow-up.',
                'starts_at' => now()->addDays(3)->setTime(20, 30)->format('Y-m-d\TH:i'),
                'ends_at' => now()->addDays(3)->setTime(22, 0)->format('Y-m-d\TH:i'),
                'event_type' => 'forum',
                'event_format' => 'hybrid',
            ],
        ])
        ->call('submit')
        ->assertHasNoErrors();

    $parentEvent = Event::query()
        ->where('title', 'Ramadan Knowledge Series')
        ->first();

    expect($parentEvent)->not->toBeNull()
        ->and($parentEvent?->isParentProgram())->toBeTrue()
        ->and((string) $parentEvent?->status)->toBe('draft')
        ->and($parentEvent?->childEvents()->count())->toBe(2);

    expect(EventSubmission::query()->where('event_id', $parentEvent?->id)->exists())->toBeTrue();

    $childTitles = $parentEvent?->childEvents()->orderBy('starts_at')->pluck('title')->all();

    expect($childTitles)->toBe(['Night One Tafsir', 'Night Two Q&A']);

    $secondChild = $parentEvent?->childEvents()->where('title', 'Night Two Q&A')->first();

    expect($secondChild)->not->toBeNull()
        ->and($secondChild?->isChildEvent())->toBeTrue()
        ->and($secondChild?->settings?->registration_required)->toBeTrue()
        ->and($secondChild?->event_format?->value)->toBe('hybrid');
});
