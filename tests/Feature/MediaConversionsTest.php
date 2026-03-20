<?php

use App\Models\DonationChannel;
use App\Models\Event;
use App\Models\Institution;
use App\Models\MembershipClaim;
use App\Models\Reference;
use App\Models\Report;
use App\Models\Series;
use App\Models\Speaker;
use App\Models\Venue;
use App\Support\Media\MediaFileNamer;
use App\Support\Media\MediaPathGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Spatie\MediaLibrary\Support\FileRemover\FileBaseFileRemover;

beforeEach(function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');
});

// ---------------------------------------------------------------
// Event model
// ---------------------------------------------------------------

it('registers media conversions for Event model', function () {
    $event = Event::factory()->create();

    $event->addMedia(UploadedFile::fake()->image('poster.jpg', 1200, 800))
        ->toMediaCollection('poster');

    $media = $event->getFirstMedia('poster');

    expect($media)->not->toBeNull();
    expect($event->hasMedia('poster'))->toBeTrue();

    $conversions = $media->getMediaConversionNames();
    expect($conversions)->toContain('thumb');
    expect($conversions)->toContain('card');
    expect($conversions)->toContain('preview');
});

it('detects portrait poster orientation', function () {
    $event = Event::factory()->create();

    $event->addMedia(UploadedFile::fake()->image('poster-portrait.jpg', 800, 1200))
        ->toMediaCollection('poster');

    expect($event->poster_orientation)->toBe('portrait');
});

it('detects landscape poster orientation', function () {
    $event = Event::factory()->create();

    $event->addMedia(UploadedFile::fake()->image('poster-landscape.jpg', 1200, 800))
        ->toMediaCollection('poster');

    expect($event->poster_orientation)->toBe('landscape');
});

it('defines accepted MIME types for Event poster collection', function () {
    $event = Event::factory()->create();

    expect(fn () => $event->addMedia(UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'))
        ->toMediaCollection('poster'))->toThrow(FileUnacceptableForCollection::class);
});

it('returns fallback URL when Event has no poster', function () {
    $event = Event::factory()->create();

    $fallbackUrl = $event->getFirstMediaUrl('poster');

    expect($fallbackUrl)->toContain('images/placeholders/event.png');
});

it('uses thumb conversion in Event card_image_url accessor', function () {
    $event = Event::factory()->create();

    $event->addMedia(UploadedFile::fake()->image('poster.jpg', 1200, 800))
        ->toMediaCollection('poster');

    $cardUrl = $event->card_image_url;

    expect($cardUrl)->toContain('poster');
});

it('falls back to placeholder in Event card_image_url when no media exists', function () {
    $event = Event::factory()->create();

    $cardUrl = $event->card_image_url;

    expect($cardUrl)->toContain('images/placeholders/event.png');
});

// ---------------------------------------------------------------
// Institution model
// ---------------------------------------------------------------

it('registers media conversions for Institution model', function () {
    $institution = Institution::factory()->create();

    $institution->addMedia(UploadedFile::fake()->image('logo.png', 400, 400))
        ->toMediaCollection('logo');

    $media = $institution->getFirstMedia('logo');

    expect($media)->not->toBeNull();
    expect($media->getMediaConversionNames())->toContain('thumb');
});

it('returns fallback URL when Institution has no logo', function () {
    $institution = Institution::factory()->create();

    $fallbackUrl = $institution->getFirstMediaUrl('logo');

    expect($fallbackUrl)->toContain('images/placeholders/institution.png');
});

it('rejects non-image files for Institution logo', function () {
    $institution = Institution::factory()->create();

    expect(fn () => $institution->addMedia(UploadedFile::fake()->create('spreadsheet.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'))
        ->toMediaCollection('logo'))->toThrow(FileUnacceptableForCollection::class);
});

it('registers media conversions for Institution gallery collection', function () {
    $institution = Institution::factory()->create();

    $institution->addMedia(UploadedFile::fake()->image('gallery.jpg', 1200, 800))
        ->toMediaCollection('gallery');

    $media = $institution->getFirstMedia('gallery');

    expect($media)->not->toBeNull();
    expect($media->getMediaConversionNames())->toContain('gallery_thumb');
});

// ---------------------------------------------------------------
// Speaker model
// ---------------------------------------------------------------

it('registers media conversions for Speaker model', function () {
    $speaker = Speaker::factory()->create();

    $speaker->addMedia(UploadedFile::fake()->image('avatar.jpg', 500, 500))
        ->toMediaCollection('avatar');

    $media = $speaker->getFirstMedia('avatar');

    expect($media)->not->toBeNull();
    expect($media->getMediaConversionNames())->toContain('thumb');
    expect($media->getMediaConversionNames())->toContain('profile');
});

it('registers media conversions for Speaker cover and gallery collections', function () {
    $speaker = Speaker::factory()->create();

    $speaker->addMedia(UploadedFile::fake()->image('cover.png', 100, 100))
        ->toMediaCollection('cover');
    $speaker->addMedia(UploadedFile::fake()->image('gallery.png', 100, 100))
        ->toMediaCollection('gallery');

    $coverMedia = $speaker->getFirstMedia('cover');
    $galleryMedia = $speaker->getFirstMedia('gallery');

    expect($coverMedia)->not->toBeNull();
    expect($galleryMedia)->not->toBeNull();
    expect($coverMedia->getMediaConversionNames())->toContain('banner');
    expect($galleryMedia->getMediaConversionNames())->toContain('gallery_thumb');
});

it('returns fallback URL when Speaker has no avatar', function () {
    $speaker = Speaker::factory()->create();

    $fallbackUrl = $speaker->getFirstMediaUrl('avatar');

    expect($fallbackUrl)->toContain('images/placeholders/speaker.png');
});

it('returns avatar_url using thumb conversion when media exists', function () {
    $speaker = Speaker::factory()->create();

    $speaker->addMedia(UploadedFile::fake()->image('avatar.jpg', 500, 500))
        ->toMediaCollection('avatar');

    $avatarUrl = $speaker->avatar_url;

    expect($avatarUrl)->not->toBeNull();
    expect($avatarUrl)->toContain('avatar');
});

// ---------------------------------------------------------------
// Venue model
// ---------------------------------------------------------------

it('registers media conversions for Venue model', function () {
    $venue = Venue::factory()->create();

    $venue->addMedia(UploadedFile::fake()->image('cover.jpg', 1200, 800))
        ->toMediaCollection('cover');

    $media = $venue->getFirstMedia('cover');

    expect($media)->not->toBeNull();
    expect($media->getMediaConversionNames())->toContain('thumb');
    expect($media->getMediaConversionNames())->toContain('banner');
});

it('returns fallback URL when Venue has no cover image', function () {
    $venue = Venue::factory()->create();

    $fallbackUrl = $venue->getFirstMediaUrl('cover');

    expect($fallbackUrl)->toContain('images/placeholders/venue.png');
});

// ---------------------------------------------------------------
// Series model
// ---------------------------------------------------------------

it('registers media conversions for Series model', function () {
    $series = Series::factory()->create();

    $series->addMedia(UploadedFile::fake()->image('cover.jpg', 800, 500))
        ->toMediaCollection('cover');

    $media = $series->getFirstMedia('cover');

    expect($media)->not->toBeNull();
    expect($media->getMediaConversionNames())->toContain('thumb');
});

// ---------------------------------------------------------------
// Reference model
// ---------------------------------------------------------------

it('registers media conversions for Reference model', function () {
    $reference = Reference::factory()->create();

    $reference->addMedia(UploadedFile::fake()->image('book-cover.jpg', 400, 560))
        ->toMediaCollection('front_cover');

    $media = $reference->getFirstMedia('front_cover');

    expect($media)->not->toBeNull();
    expect($media->getMediaConversionNames())->toContain('thumb');
});

// ---------------------------------------------------------------
// Report model
// ---------------------------------------------------------------

it('registers media conversions for Report model', function () {
    $report = Report::factory()->create();

    $report->addMedia(UploadedFile::fake()->image('evidence.jpg', 800, 600))
        ->toMediaCollection('evidence');

    $media = $report->getFirstMedia('evidence');

    expect($media)->not->toBeNull();
    expect($media->getMediaConversionNames())->toContain('thumb');
});

it('registers media conversions for MembershipClaim model', function () {
    $claim = MembershipClaim::factory()->create();

    $claim->addMedia(UploadedFile::fake()->image('claim-evidence.jpg', 800, 600))
        ->toMediaCollection('evidence');

    $media = $claim->getFirstMedia('evidence');

    expect($media)->not->toBeNull();
    expect($media->getMediaConversionNames())->toContain('thumb');
});

it('accepts PDF files for Report evidence', function () {
    $report = Report::factory()->create();

    // Create a real temporary PDF file so MIME detection works correctly
    $pdfPath = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($pdfPath, '%PDF-1.4 test content');

    $report->addMedia($pdfPath)
        ->usingFileName('document.pdf')
        ->toMediaCollection('evidence');

    expect($report->hasMedia('evidence'))->toBeTrue();
});

// ---------------------------------------------------------------
// DonationChannel model
// ---------------------------------------------------------------

it('registers media conversions for DonationChannel model', function () {
    $channel = DonationChannel::factory()->create();

    $channel->addMedia(UploadedFile::fake()->image('qr.png', 300, 300))
        ->toMediaCollection('qr');

    $media = $channel->getFirstMedia('qr');

    expect($media)->not->toBeNull();
    expect($media->getMediaConversionNames())->toContain('thumb');
});

// ---------------------------------------------------------------
// Cross-cutting media library config
// ---------------------------------------------------------------

it('has lazy loading set as default in media library config', function () {
    expect(config('media-library.default_loading_attribute_value'))->toBe('lazy');
});

it('uses custom path generator', function () {
    expect(config('media-library.path_generator'))->toBe(MediaPathGenerator::class);
});

it('uses custom file namer', function () {
    expect(config('media-library.file_namer'))->toBe(MediaFileNamer::class);
});

it('uses file-based media remover strategy for shared directories', function () {
    expect(config('media-library.file_remover_class'))
        ->toBe(FileBaseFileRemover::class);
});

it('enables media url versioning for cache busting', function () {
    expect(config('media-library.version_urls'))->toBeTrue();
});
