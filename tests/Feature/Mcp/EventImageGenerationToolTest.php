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
use App\Mcp\Tools\Admin\AdminGenerateEventCoverImageTool;
use App\Mcp\Tools\Admin\AdminGetRecordActionsTool;
use App\Mcp\Tools\Member\MemberGenerateEventPosterImageTool;
use App\Mcp\Tools\Member\MemberGetRecordActionsTool;
use App\Models\Event;
use App\Models\EventKeyPerson;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Series;
use App\Models\Speaker;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Image;
use Laravel\Ai\Prompts\ImagePrompt;
use Laravel\Mcp\Server\Testing\TestResponse as McpTestResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    config()->set('media-library.disk_name', 'public');
    Storage::fake('public');
});

it('generates and stores an admin 16:9 event cover image with selected relation media', function (): void {
    Image::fake([eventImageBase64Fixture('generated-cover.png', 1200, 800)]);

    $admin = eventImageGenerationAdminUser();
    [$event, $speaker, $reference] = eventImageGenerationEventFixture();

    $response = AdminServer::actingAs($admin)
        ->tool(AdminGenerateEventCoverImageTool::class, [
            'event_key' => $event->slug,
            'creative_direction' => 'Use deep emerald, warm gold, and premium Malay editorial typography.',
            'max_reference_media' => 3,
        ])
        ->assertOk()
        ->assertSee('Generated cover image')
        ->assertStructuredContent(fn ($json) => $json
            ->where('event.route_key', $event->getRouteKey())
            ->where('event.slug', $event->slug)
            ->where('target.collection', 'cover')
            ->where('target.aspect_ratio', '16:9')
            ->where('upload_spec.target_collection', 'cover')
            ->where('upload_spec.required_aspect_ratio', '16:9')
            ->where('generated_media.collection', 'cover')
            ->where('generated_media.required_aspect_ratio', '16:9')
            ->where('generation.requested_ai_size', '3:2')
            ->where('prompt', fn (string $prompt): bool => str_contains($prompt, 'website and mobile app display')
                && str_contains($prompt, 'not the full marketing flyer')
                && str_contains($prompt, 'deep emerald')
                && str_contains($prompt, 'Tadabbur: Isu Semasa Ummah'))
            ->where('source_data.relations.references', fn (mixed $references): bool => collect($references)
                ->contains(fn (mixed $item): bool => is_array($item)
                    && str_contains((string) data_get($item, 'display_title'), 'Tafsir Ibn Kathir')))
            ->where('reference_media', fn (mixed $media): bool => collect($media)
                ->contains(fn (mixed $item): bool => is_array($item) && data_get($item, 'role') === 'institution_cover')
                && collect($media)->contains(fn (mixed $item): bool => is_array($item) && data_get($item, 'role') === 'speaker_avatar:speaker')
                && collect($media)->contains(fn (mixed $item): bool => is_array($item) && data_get($item, 'role') === 'reference_front_cover'))
            ->etc());

    $responseArray = eventImageGenerationMcpResponseArray($response);
    $contentTypes = collect(data_get($responseArray, 'result.content', []))->pluck('type')->all();

    expect($contentTypes)->toContain('text', 'image');

    $coverMedia = $event->fresh()->getFirstMedia('cover');

    expect($coverMedia)->toBeInstanceOf(Media::class)
        ->and(eventImageDimensions($coverMedia))->toBe([1600, 900])
        ->and($reference->getFirstMedia('front_cover'))->not->toBeNull();

    Image::assertGenerated(fn (ImagePrompt $prompt): bool => $prompt->isLandscape()
        && $prompt->quality === 'high'
        && $prompt->attachments->count() === 3
        && $prompt->contains('Required aspect ratio: 16:9'));

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
            ) === 'admin-generate-event-cover-image'
                && data_get(
                    collect($actions)->firstWhere('key', 'generate_event_poster_image'),
                    'tool',
                ) === 'admin-generate-event-poster-image')
            ->etc());
});

it('generates and stores a member 4:5 event poster only for accessible events', function (): void {
    Image::fake([eventImageBase64Fixture('generated-poster.png', 1000, 1500)]);

    [$member, $institution] = eventImageGenerationMemberContext();
    [$event] = eventImageGenerationEventFixture($institution);

    MemberServer::actingAs($member)
        ->tool(MemberGenerateEventPosterImageTool::class, [
            'event_key' => $event->slug,
            'creative_direction' => 'Make the social poster information-rich but still premium.',
            'max_reference_media' => 2,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('event.route_key', $event->getRouteKey())
            ->where('target.collection', 'poster')
            ->where('target.aspect_ratio', '4:5')
            ->where('upload_spec.target_collection', 'poster')
            ->where('upload_spec.required_aspect_ratio', '4:5')
            ->where('generated_media.collection', 'poster')
            ->where('generation.requested_ai_size', '2:3')
            ->where('prompt', fn (string $prompt): bool => str_contains($prompt, 'external distribution')
                && str_contains($prompt, 'Required visible event facts')
                && str_contains($prompt, 'Speaker(s):'))
            ->etc());

    $posterMedia = $event->fresh()->getFirstMedia('poster');

    expect($posterMedia)->toBeInstanceOf(Media::class)
        ->and(eventImageDimensions($posterMedia))->toBe([1600, 2000]);

    Image::assertGenerated(fn (ImagePrompt $prompt): bool => $prompt->isPortrait()
        && $prompt->quality === 'high'
        && $prompt->attachments->count() === 2
        && $prompt->contains('Required aspect ratio: 4:5'));

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
            ) === 'member-generate-event-cover-image'
                && data_get(
                    collect($actions)->firstWhere('key', 'generate_event_poster_image'),
                    'tool',
                ) === 'member-generate-event-poster-image')
            ->etc());

    $inaccessibleEvent = Event::factory()->create([
        'title' => 'Unrelated Member Event',
        'slug' => 'unrelated-member-event',
        'institution_id' => Institution::factory()->create()->getKey(),
        'status' => 'approved',
    ]);

    MemberServer::actingAs($member)
        ->tool(MemberGenerateEventPosterImageTool::class, [
            'event_key' => $inaccessibleEvent->slug,
        ])
        ->assertSee('Resource not found.');
});

it('exposes mutating and open-world metadata for event image generation tools', function (): void {
    $adminTool = app(AdminGenerateEventCoverImageTool::class)->toArray();
    $memberTool = app(MemberGenerateEventPosterImageTool::class)->toArray();

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

    expect(data_get($adminTool, '_meta.openai/toolInvocation/invoking'))->toBe('Generating event cover image...')
        ->and(data_get($memberTool, 'inputSchema.properties.aspect_ratio'))->toBeNull()
        ->and(data_get($memberTool, 'inputSchema.properties.max_reference_media.maximum'))->toBe(8);
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

    /** @var MorphTo<\Illuminate\Database\Eloquent\Model, Event> $organizerRelation */
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

function eventImageBase64Fixture(string $name, int $width, int $height): string
{
    $upload = fakeGeneratedImageUpload($name, $width, $height);
    $contents = file_get_contents($upload->getRealPath());

    if (! is_string($contents)) {
        throw new RuntimeException('Unable to read generated image fixture.');
    }

    return base64_encode($contents);
}

/**
 * @return array{0: int, 1: int}
 */
function eventImageDimensions(?Media $media): array
{
    expect($media)->toBeInstanceOf(Media::class);

    $dimensions = getimagesize($media->getPath());

    expect($dimensions)->toBeArray();

    return [(int) $dimensions[0], (int) $dimensions[1]];
}

/**
 * @return array<string, mixed>
 */
function eventImageGenerationMcpResponseArray(McpTestResponse $response): array
{
    /** @var array<string, mixed> $responseArray */
    $responseArray = (fn (): array => $this->response->toArray())->call($response);

    return $responseArray;
}
