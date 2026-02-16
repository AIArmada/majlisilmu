<?php

use App\Models\Speaker;

it('renders social media section below biodata on speaker show page', function () {
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'bio' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => 'Biodata test speaker.',
                ]],
            ]],
        ],
    ]);

    $speaker->socialMedia()->create([
        'platform' => 'website',
        'url' => 'https://example.com',
        'username' => 'example',
    ]);

    $this->get(route('speakers.show', $speaker))
        ->assertSuccessful()
        ->assertSee('Biodata')
        ->assertSee('Media Sosial')
        ->assertSeeInOrder(['Biodata', 'Media Sosial']);
});
