<?php

use App\Forms\SpeakerFormSchema;
use App\Models\Institution;
use App\Models\Speaker;

it('includes biography, cover image, and institution position fields in speaker create option form', function () {
    $components = collect(SpeakerFormSchema::createOptionForm())
        ->keyBy(fn (mixed $component): ?string => method_exists($component, 'getName') ? $component->getName() : null);

    $fieldNames = $components
        ->keys()
        ->filter()
        ->values()
        ->all();

    expect($fieldNames)->toContain('bio');
    expect($fieldNames)->toContain('cover');
    expect($fieldNames)->toContain('institution_position');
    expect($fieldNames)->toContain('institution_id');
    expect($components->get('institution_id')?->isMultiple())->toBeFalse();
});

it('stores biography and institution pivot position when creating a speaker via create option', function () {
    $institution = Institution::factory()->create();

    $bio = [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [[
                'type' => 'text',
                'text' => 'Biografi ujian penceramah.',
            ]],
        ]],
    ];

    $speakerId = SpeakerFormSchema::createOptionUsing([
        'name' => 'Ustaz Test Speaker',
        'gender' => 'male',
        'bio' => $bio,
        'institution_id' => $institution->id,
        'institution_position' => 'Mudir',
    ]);

    /** @var Speaker $speaker */
    $speaker = Speaker::query()
        ->with('institutions')
        ->findOrFail($speakerId);

    $linkedInstitution = $speaker->institutions->firstWhere('id', $institution->id);

    expect($speaker->bio)->toBe($bio)
        ->and($speaker->status)->toBe('pending')
        ->and($linkedInstitution)->not->toBeNull()
        ->and($linkedInstitution?->pivot?->position)->toBe('Mudir');
});
