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
use App\Mcp\Tools\Admin\AdminGenerateEventCoverPromptTool;
use App\Mcp\Tools\Admin\AdminGetRecordActionsTool;
use App\Mcp\Tools\Member\MemberGenerateEventCoverPromptTool;
use App\Mcp\Tools\Member\MemberGetRecordActionsTool;
use App\Models\Event;
use App\Models\EventKeyPerson;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Series;
use App\Models\Speaker;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Testing\TestResponse as McpTestResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    config()->set('media-library.disk_name', 'public');
    Storage::fake('public');
});

it('builds an admin event cover prompt with selected relation media and upload spec', function (): void {
    $admin = eventCoverPromptAdminUser();
    [$event, $speaker, $reference] = eventCoverPromptEventFixture();

    $response = AdminServer::actingAs($admin)
        ->tool(AdminGenerateEventCoverPromptTool::class, [
            'event_key' => $event->slug,
            'aspect_ratio' => '4:5',
            'creative_direction' => 'Use deep emerald, warm gold, and premium Malay editorial typography.',
            'embed_selected_media' => true,
            'max_embedded_media' => 3,
        ])
        ->assertOk()
        ->assertSee('Create a stunning, premium Majlis Ilmu event cover image.')
        ->assertStructuredContent(fn ($json) => $json
            ->where('event.route_key', $event->getRouteKey())
            ->where('event.slug', $event->slug)
            ->where('event.title', 'Tadabbur: Isu Semasa Ummah')
            ->where('upload_spec.target_collection', 'poster')
            ->where('upload_spec.single_file', true)
            ->where('upload_spec.recommended_aspect_ratio', '4:5')
            ->where('prompt', fn (string $prompt): bool => str_contains($prompt, 'Tadabbur: Isu Semasa Ummah')
                && str_contains($prompt, $speaker->formatted_name)
                && str_contains($prompt, 'Tafsir Ibn Kathir')
                && str_contains($prompt, 'deep emerald'))
            ->where('source_data.direct_attributes.slug', $event->slug)
            ->where('source_data.relations.references', fn (mixed $references): bool => collect($references)
                ->contains(fn (mixed $item): bool => is_array($item)
                    && str_contains((string) data_get($item, 'display_title'), 'Tafsir Ibn Kathir')))
            ->where('reference_media', fn (mixed $media): bool => collect($media)
                ->contains(fn (mixed $item): bool => is_array($item) && data_get($item, 'role') === 'institution_cover')
                && collect($media)->contains(fn (mixed $item): bool => is_array($item) && data_get($item, 'role') === 'speaker_avatar:speaker')
                && collect($media)->contains(fn (mixed $item): bool => is_array($item) && data_get($item, 'role') === 'reference_front_cover'))
            ->etc());

    $responseArray = eventCoverPromptMcpResponseArray($response);
    $contentTypes = collect(data_get($responseArray, 'result.content', []))->pluck('type')->all();

    expect($contentTypes)->toContain('text', 'image');
    expect(data_get($responseArray, 'result.structuredContent.reference_media.0.embedded_in_mcp_content'))->toBeTrue();

    AdminServer::actingAs($admin)
        ->tool(AdminGetRecordActionsTool::class, [
            'resource_key' => 'events',
            'record_key' => $event->getRouteKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.focus_actions.actions', fn (mixed $actions): bool => data_get(
                collect($actions)->firstWhere('key', 'generate_event_cover_prompt'),
                'tool',
            ) === 'admin-generate-event-cover-prompt')
            ->etc());

    expect($reference->getFirstMedia('front_cover'))->not->toBeNull();
});

it('builds a member event cover prompt only for accessible events', function (): void {
    [$member, $institution] = eventCoverPromptMemberContext();
    [$event] = eventCoverPromptEventFixture($institution);

    MemberServer::actingAs($member)
        ->tool(MemberGenerateEventCoverPromptTool::class, [
            'event_key' => $event->slug,
            'aspect_ratio' => 'auto',
            'embed_selected_media' => false,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('event.route_key', $event->getRouteKey())
            ->where('event.slug', $event->slug)
            ->where('upload_spec.target_collection', 'poster')
            ->where('prompt', fn (string $prompt): bool => str_contains($prompt, 'Tadabbur: Isu Semasa Ummah'))
            ->etc());

    MemberServer::actingAs($member)
        ->tool(MemberGetRecordActionsTool::class, [
            'resource_key' => 'events',
            'record_key' => $event->getRouteKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.focus_actions.actions', fn (mixed $actions): bool => data_get(
                collect($actions)->firstWhere('key', 'generate_event_cover_prompt'),
                'tool',
            ) === 'member-generate-event-cover-prompt')
            ->etc());

    $inaccessibleEvent = Event::factory()->create([
        'title' => 'Unrelated Member Event',
        'slug' => 'unrelated-member-event',
        'institution_id' => Institution::factory()->create()->getKey(),
        'status' => 'approved',
    ]);

    MemberServer::actingAs($member)
        ->tool(MemberGenerateEventCoverPromptTool::class, [
            'event_key' => $inaccessibleEvent->slug,
        ])
        ->assertSee('Resource not found.');
});

it('exposes read-only metadata for event cover prompt tools', function (): void {
    $adminTool = app(AdminGenerateEventCoverPromptTool::class)->toArray();
    $memberTool = app(MemberGenerateEventCoverPromptTool::class)->toArray();

    expect($adminTool['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => true,
        'idempotentHint' => true,
        'destructiveHint' => false,
        'openWorldHint' => false,
    ]);

    expect($memberTool['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => true,
        'idempotentHint' => true,
        'destructiveHint' => false,
        'openWorldHint' => false,
    ]);

    expect(data_get($adminTool, '_meta.openai/toolInvocation/invoking'))->toBe('Building event cover prompt...');
    expect(data_get($memberTool, 'inputSchema.properties.aspect_ratio.enum'))->toBe(['auto', '16:9', '4:5']);
});

/**
 * @return array{0: Event, 1: Speaker, 2: Reference, 3: Institution}
 */
function eventCoverPromptEventFixture(?Institution $institution = null): array
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

function eventCoverPromptAdminUser(): User
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
function eventCoverPromptMemberContext(): array
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

/**
 * @return array<string, mixed>
 */
function eventCoverPromptMcpResponseArray(McpTestResponse $response): array
{
    /** @var array<string, mixed> $responseArray */
    $responseArray = (fn (): array => $this->response->toArray())->call($response);

    return $responseArray;
}
