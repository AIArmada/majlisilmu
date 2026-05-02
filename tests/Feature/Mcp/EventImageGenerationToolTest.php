<?php

use App\Actions\Membership\AddMemberToSubject;
use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventKeyPersonRole;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\ReferenceType;
use App\Mcp\Servers\AdminServer;
use App\Mcp\Servers\MemberServer;
use App\Mcp\Tools\Admin\AdminGetRecordActionsTool;
use App\Mcp\Tools\Admin\AdminUploadEventCoverImageTool;
use App\Mcp\Tools\Member\MemberGetRecordActionsTool;
use App\Mcp\Tools\Member\MemberUploadEventCoverImageTool;
use App\Mcp\Tools\Member\MemberUploadEventPosterImageTool;
use App\Models\Event;
use App\Models\EventKeyPerson;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Series;
use App\Models\Speaker;
use App\Models\User;
use App\Support\Mcp\EventImageGenerationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Files\RemoteImage;
use Laravel\Ai\Files\StoredImage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    config()->set('media-library.disk_name', 'public');
    Storage::fake('public');
});

it('uploads and stores an admin 16:9 event cover image via base64 descriptor', function (): void {
    $admin = eventImageGenerationAdminUser();
    [$event] = eventImageGenerationEventFixture();

    $imageFixture = fakeGeneratedImageUpload('generated-cover.jpg', 1200, 800);
    $contents = file_get_contents($imageFixture->getRealPath());
    expect($contents)->toBeString();

    AdminServer::actingAs($admin)
        ->tool(AdminUploadEventCoverImageTool::class, [
            'event_key' => $event->slug,
            'image' => [
                'filename' => 'generated-cover.jpg',
                'content_base64' => base64_encode((string) $contents),
                'mime_type' => 'image/jpeg',
            ],
            'creative_direction' => 'Use deep emerald, warm gold.',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('event.route_key', $event->getRouteKey())
            ->where('event.slug', $event->slug)
            ->where('collection', 'cover')
            ->has('media.id')
            ->has('media.url')
            ->etc());

    $coverMedia = $event->fresh()->getFirstMedia('cover');

    expect($coverMedia)->toBeInstanceOf(Media::class)
        ->and((string) $coverMedia->collection_name)->toBe('cover');

    AdminServer::actingAs($admin)
        ->tool(AdminGetRecordActionsTool::class, [
            'resource_key' => 'events',
            'record_key' => $event->getRouteKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.focus_actions.actions', fn (mixed $actions): bool => data_get(
                collect($actions)->firstWhere('key', 'generate_event_cover_image'),
                'tool',
            ) === 'admin-upload-event-cover-image'
                && data_get(
                    collect($actions)->firstWhere('key', 'generate_event_poster_image'),
                    'tool',
                ) === 'admin-upload-event-poster-image')
            ->etc());
});

it('uploads and stores a member 4:5 event poster only for accessible events', function (): void {
    [$member, $institution] = eventImageGenerationMemberContext();
    [$event] = eventImageGenerationEventFixture($institution);

    $imageFixture = fakeGeneratedImageUpload('generated-poster.jpg', 800, 1000);
    $contents = file_get_contents($imageFixture->getRealPath());
    expect($contents)->toBeString();

    MemberServer::actingAs($member)
        ->tool(MemberUploadEventPosterImageTool::class, [
            'event_key' => $event->slug,
            'image' => [
                'filename' => 'generated-poster.jpg',
                'content_base64' => base64_encode((string) $contents),
                'mime_type' => 'image/jpeg',
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('event.route_key', $event->getRouteKey())
            ->where('collection', 'poster')
            ->has('media.id')
            ->etc());

    $posterMedia = $event->fresh()->getFirstMedia('poster');

    expect($posterMedia)->toBeInstanceOf(Media::class)
        ->and((string) $posterMedia->collection_name)->toBe('poster');

    MemberServer::actingAs($member)
        ->tool(MemberGetRecordActionsTool::class, [
            'resource_key' => 'events',
            'record_key' => $event->getRouteKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.focus_actions.actions', fn (mixed $actions): bool => data_get(
                collect($actions)->firstWhere('key', 'generate_event_cover_image'),
                'tool',
            ) === 'member-upload-event-cover-image'
                && data_get(
                    collect($actions)->firstWhere('key', 'generate_event_poster_image'),
                    'tool',
                ) === 'member-upload-event-poster-image')
            ->etc());

    $inaccessibleEvent = Event::factory()->create([
        'title' => 'Unrelated Member Event',
        'slug' => 'unrelated-member-event',
        'institution_id' => Institution::factory()->create()->getKey(),
        'status' => 'approved',
    ]);

    MemberServer::actingAs($member)
        ->tool(MemberUploadEventPosterImageTool::class, [
            'event_key' => $inaccessibleEvent->slug,
            'image' => ['filename' => 'x.jpg', 'content_base64' => 'dGVzdA==', 'mime_type' => 'image/jpeg'],
        ])
        ->assertSee('Resource not found.');
});

it('accepts a JSON-encoded string image descriptor (ChatGPT openai/fileParams serialization)', function (): void {
    $admin = eventImageGenerationAdminUser();
    [$event] = eventImageGenerationEventFixture();

    $imageFixture = fakeGeneratedImageUpload('chatgpt-cover.jpg', 1200, 800);
    $contents = file_get_contents($imageFixture->getRealPath());
    expect($contents)->toBeString();

    $descriptorString = json_encode([
        'filename' => 'chatgpt-cover.jpg',
        'content_base64' => base64_encode((string) $contents),
        'mime_type' => 'image/jpeg',
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminUploadEventCoverImageTool::class, [
            'event_key' => $event->slug,
            'image' => $descriptorString,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('event.route_key', $event->getRouteKey())
            ->where('collection', 'cover')
            ->has('media.id')
            ->etc());

    expect($event->fresh()->getFirstMedia('cover'))->toBeInstanceOf(Media::class);
});

it('rejects an invalid image descriptor (neither array nor valid JSON object)', function (): void {
    $admin = eventImageGenerationAdminUser();
    [$event] = eventImageGenerationEventFixture();

    AdminServer::actingAs($admin)
        ->tool(AdminUploadEventCoverImageTool::class, [
            'event_key' => $event->slug,
            'image' => 'not-valid-json',
        ])
        ->assertSee('The image must be a file descriptor object');
});

it('exposes mutating and open-world metadata for event image upload tools', function (): void {
    $adminTool = app(AdminUploadEventCoverImageTool::class)->toArray();
    $memberTool = app(MemberUploadEventCoverImageTool::class)->toArray();

    expect($adminTool['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => false,
        'idempotentHint' => false,
        'destructiveHint' => true,
        'openWorldHint' => true,
    ]);

    expect($memberTool['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => false,
        'idempotentHint' => false,
        'destructiveHint' => true,
        'openWorldHint' => true,
    ]);

    expect(data_get($adminTool, '_meta.openai/toolInvocation/invoking'))->toBe('Uploading event cover image...')
        ->and(data_get($memberTool, '_meta.openai/toolInvocation/invoked'))->toBe('Event cover image uploaded.')
        ->and(data_get($adminTool, 'inputSchema.properties.image'))->toBeArray();
});

it('uses storage-backed image attachments instead of remote url attachments', function (): void {
    Storage::fake('s3');

    $event = Event::factory()->create([
        'institution_id' => Institution::factory()->create()->getKey(),
        'title' => 'Attachment Safety Event',
        'slug' => 'attachment-safety-event',
        'status' => 'approved',
        'is_active' => true,
    ]);

    $event
        ->addMedia(fakeGeneratedImageUpload('attachment-safety-cover.jpg', 1600, 900))
        ->toMediaCollection('cover', 's3');

    $media = $event->getFirstMedia('cover');

    expect($media)->toBeInstanceOf(Media::class);

    $service = app(EventImageGenerationService::class);
    $method = new ReflectionMethod(EventImageGenerationService::class, 'attachmentForMedia');
    $method->setAccessible(true);

    $attachment = $method->invoke($service, $media);

    expect($attachment)
        ->toBeInstanceOf(StoredImage::class)
        ->not->toBeInstanceOf(RemoteImage::class);
});

/**
 * @return array{0: Event, 1: Speaker, 2: Reference, 3: Institution}
 */
function eventImageGenerationEventFixture(?Institution $institution = null): array
{
    $institution ??= Institution::factory()->create([
        'name' => 'Masjid Al-Falah',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $institution
        ->addMedia(fakeGeneratedImageUpload('institution-cover.jpg', 1600, 900))
        ->toMediaCollection('cover');

    $speaker = Speaker::factory()->create([
        'name' => 'Dr. MAZA',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $speaker
        ->addMedia(fakeGeneratedImageUpload('speaker-avatar.jpg', 800, 800))
        ->toMediaCollection('avatar');

    $reference = Reference::factory()->create([
        'title' => 'Tafsir Ibn Kathir',
        'author' => 'Imam Ibn Kathir',
        'type' => ReferenceType::Book->value,
        'status' => 'verified',
        'is_active' => true,
    ]);

    $reference
        ->addMedia(fakeGeneratedImageUpload('reference-front-cover.jpg', 800, 1200))
        ->toMediaCollection('front_cover');

    $series = Series::factory()->create([
        'title' => 'Tadabbur Semasa',
        'is_active' => true,
    ]);

    $series
        ->addMedia(fakeGeneratedImageUpload('series-cover.jpg', 1600, 900))
        ->toMediaCollection('cover');

    $event = Event::factory()->create([
        'institution_id' => $institution->getKey(),
        'organizer_type' => Institution::class,
        'organizer_id' => $institution->getKey(),
        'title' => 'Tadabbur: Isu Semasa Ummah',
        'slug' => 'tadabbur-isu-semasa-ummah-qdkhqqn',
        'description' => 'Kupasan tadabbur al-Quran untuk memahami isu semasa umat.',
        'starts_at' => Carbon::parse('2026-05-09 20:30:00', 'Asia/Kuala_Lumpur')->utc(),
        'ends_at' => Carbon::parse('2026-05-09 22:00:00', 'Asia/Kuala_Lumpur')->utc(),
        'timezone' => 'Asia/Kuala_Lumpur',
        'event_type' => [EventType::Tazkirah->value],
        'gender' => EventGenderRestriction::All->value,
        'age_group' => [EventAgeGroup::AllAges->value],
        'children_allowed' => true,
        'event_format' => EventFormat::Physical->value,
        'visibility' => EventVisibility::Public->value,
        'status' => 'approved',
        'is_active' => true,
    ]);

    /** @var MorphTo<Model, Event> $organizerRelation */
    $organizerRelation = $event->organizer();

    expect($organizerRelation->getMorphType())->toBe('organizer_type')
        ->and($event->organizer_type)->toBe(Institution::class)
        ->and($event->organizer_id)->toBe($institution->getKey());

    EventKeyPerson::factory()->create([
        'event_id' => $event->getKey(),
        'speaker_id' => $speaker->getKey(),
        'role' => EventKeyPersonRole::Speaker->value,
        'order_column' => 1,
        'is_public' => true,
    ]);

    $event->references()->attach($reference->getKey(), ['order_column' => 1]);
    $event->series()->attach($series->getKey(), [
        'id' => (string) Str::uuid(),
        'order_column' => 1,
    ]);

    return [$event->refresh(), $speaker, $reference, $institution];
}

function eventImageGenerationAdminUser(): User
{
    $role = 'super_admin';

    if (! Role::query()->where('name', $role)->where('guard_name', 'web')->exists()) {
        $roleRecord = new Role;
        $roleRecord->forceFill([
            'id' => (string) Str::uuid(),
            'name' => $role,
            'guard_name' => 'web',
        ])->save();
    }

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * @return array{0: User, 1: Institution}
 */
function eventImageGenerationMemberContext(): array
{
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $member = User::factory()->create([
        'phone' => '+60112223344',
        'phone_verified_at' => now(),
    ]);

    app(AddMemberToSubject::class)->handle($institution, $member, 'admin');

    return [$member, $institution];
}
