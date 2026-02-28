<?php

use App\Enums\SocialMediaPlatform;
use App\Models\Institution;
use App\Models\Speaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('extracts instagram username from full profile url and resolves canonical url', function () {
    $speaker = Speaker::factory()->create();

    $social = $speaker->socialMedia()->create([
        'platform' => SocialMediaPlatform::Instagram->value,
        'url' => 'https://www.instagram.com/ustazah.aminah/?hl=en',
    ])->fresh();

    expect($social->username)->toBe('ustazah.aminah')
        ->and($social->url)->toBeNull()
        ->and($social->resolved_url)->toBe('https://www.instagram.com/ustazah.aminah');
});

it('accepts @handle input and resolves a tiktok url', function () {
    $speaker = Speaker::factory()->create();

    $social = $speaker->socialMedia()->create([
        'platform' => SocialMediaPlatform::TikTok->value,
        'username' => '@majlisilmu',
    ])->fresh();

    expect($social->username)->toBe('majlisilmu')
        ->and($social->url)->toBeNull()
        ->and($social->resolved_url)->toBe('https://www.tiktok.com/@majlisilmu');
});

it('normalizes x links under twitter platform and canonical x url', function () {
    $institution = Institution::factory()->create();

    $social = $institution->socialMedia()->create([
        'platform' => 'x',
        'url' => 'https://x.com/majlisilmu',
    ])->fresh();

    expect($social->platform)->toBe(SocialMediaPlatform::Twitter->value)
        ->and($social->username)->toBe('majlisilmu')
        ->and($social->url)->toBeNull()
        ->and($social->resolved_url)->toBe('https://x.com/majlisilmu');
});

it('keeps selected handle platform when platform is given as enum instance', function () {
    $speaker = Speaker::factory()->create();

    $social = $speaker->socialMedia()->create([
        'platform' => SocialMediaPlatform::Facebook,
        'username' => 'nurul',
        'url' => null,
    ])->fresh();

    expect($social->platform)->toBe(SocialMediaPlatform::Facebook->value)
        ->and($social->username)->toBe('nurul')
        ->and($social->url)->toBeNull()
        ->and($social->resolved_url)->toBe('https://www.facebook.com/nurul');
});

it('normalizes website url when pasted into username field', function () {
    $institution = Institution::factory()->create();

    $social = $institution->socialMedia()->create([
        'platform' => SocialMediaPlatform::Website->value,
        'username' => 'majlisilmu.test/profile',
    ])->fresh();

    expect($social->username)->toBeNull()
        ->and($social->url)->toBe('https://majlisilmu.test/profile')
        ->and($social->resolved_url)->toBe('https://majlisilmu.test/profile');
});

it('renders resolved social url on speaker page when url column is null', function () {
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
    ]);

    $speaker->socialMedia()->create([
        'platform' => SocialMediaPlatform::Instagram->value,
        'username' => 'ustazah.aminah',
    ]);

    $this->get(route('speakers.show', $speaker))
        ->assertSuccessful()
        ->assertSee('https://www.instagram.com/ustazah.aminah', false);
});
