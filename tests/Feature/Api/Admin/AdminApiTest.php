<?php

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\RegistrationMode;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Series;
use App\Models\Space;
use App\Models\Speaker;
use App\Models\Subdistrict;
use App\Models\Tag;
use App\Models\User;
use App\Models\Venue;
use App\Support\Location\FederalTerritoryLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('rejects users without admin panel access from the admin api manifest', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/admin/manifest')
        ->assertForbidden();
});

it('lists accessible admin resources for privileged users', function () {
    $admin = adminApiUser('super_admin');

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/admin/manifest')
        ->assertOk();

    expect($response->json('data.version'))->toBe('2026-04-16')
        ->and($response->json('data.docs.ui'))->toBe('https://api.majlisilmu.test/docs')
        ->and($response->json('data.docs.openapi'))->toBe('https://api.majlisilmu.test/docs.json')
        ->and($response->json('data.write_workflow.discover_resources'))->toContain('/api/v1/admin/manifest')
        ->and($response->json('data.rules'))->toContain('Use the admin record route_key returned by admin collection or record endpoints for record-specific paths.');

    $resourceKeys = collect($response->json('data.resources'))->pluck('key')->all();

    expect($resourceKeys)->toContain('speakers', 'events', 'institutions', 'references', 'subdistricts', 'venues');
});

it('allows viewer-role users who can access the admin panel to reach the admin api manifest', function () {
    $viewer = adminApiUser('viewer');

    Sanctum::actingAs($viewer);

    $this->getJson('/api/v1/admin/manifest')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'resources' => [
                    ['key'],
                ],
            ],
        ]);
});

it('does not elevate admin manifest access from bearer token abilities alone', function () {
    $nonAdmin = User::factory()->create();
    $nonAdminToken = $nonAdmin->createToken('non-admin-device', ['admin.manifest'])->plainTextToken;

    $this->withToken($nonAdminToken)
        ->getJson('/api/v1/admin/manifest')
        ->assertForbidden();
});

it('uses the authenticated token user roles for admin manifest access without token abilities', function () {
    $viewer = adminApiUser('viewer');
    $viewerToken = $viewer->createToken('viewer-device', [])->plainTextToken;

    $this->withToken($viewerToken)
        ->getJson('/api/v1/admin/manifest')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'resources' => [
                    ['key'],
                ],
            ],
        ]);
});

it('reflects global admin role grants and removals on an existing bearer token', function () {
    setPermissionsTeamId(null);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    if (! Role::query()->where('name', 'viewer')->where('guard_name', 'web')->exists()) {
        $roleRecord = new Role;
        $roleRecord->forceFill([
            'id' => (string) Str::uuid(),
            'name' => 'viewer',
            'guard_name' => 'web',
        ])->save();
    }

    $user = User::factory()->create();
    $token = $user->createToken('role-drift-check', [])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/admin/manifest')
        ->assertForbidden();

    $user->assignRole('viewer');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->withToken($token)
        ->getJson('/api/v1/admin/manifest')
        ->assertOk();

    $user->syncRoles([]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->withToken($token)
        ->getJson('/api/v1/admin/manifest')
        ->assertForbidden();
});

it('returns admin speaker resource metadata and records', function () {
    $admin = adminApiUser('super_admin');
    $speaker = Speaker::factory()->create([
        'name' => 'Admin API Speaker',
    ]);
    $speakerRouteKey = (string) $speaker->getRouteKey();

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/speakers/meta')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'speakers')
        ->assertJsonPath('data.resource.pages.index', true)
        ->assertJsonPath('data.resource.abilities.view_any', true)
        ->assertJsonPath('data.resource.write_support.schema', true)
        ->assertJsonPath('data.resource.api_routes.collection', '/api/v1/admin/speakers')
        ->assertJsonPath('data.resource.api_routes.schema', '/api/v1/admin/speakers/schema');

    $this->getJson('/api/v1/admin/speakers?search=Admin%20API%20Speaker')
        ->assertOk()
        ->assertJsonPath('data.0.id', $speaker->getKey())
        ->assertJsonPath('data.0.title', 'Admin API Speaker')
        ->assertJsonPath('data.0.abilities.view', true);

    $this->getJson('/api/v1/admin/speakers/'.$speakerRouteKey)
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'speakers')
        ->assertJsonPath('data.record.id', $speaker->getKey())
        ->assertJsonPath('data.record.route_key', $speakerRouteKey)
        ->assertJsonPath('data.record.attributes.name', 'Admin API Speaker')
        ->assertJsonPath('data.record.abilities.view', true);
});

it('exposes admin speaker write schema and can create and update speakers through the api', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $schema = $this->getJson('/api/v1/admin/speakers/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.schema.resource_key', 'speakers')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.slug_behavior', 'auto_managed')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/speakers')
        ->json('data.schema');

    $speakerFields = collect($schema['fields'] ?? [])->pluck('name')->all();

    expect(collect($schema['catalogs'] ?? [])->pluck('field')->all())
        ->toContain('address.country_id')
        ->toContain('address.state_id', 'address.district_id', 'address.subdistrict_id')
        ->and($speakerFields)->toContain('address.country_id')
        ->and($speakerFields)->not->toContain('address.country_code', 'address.country_key')
        ->and(collect($schema['conditional_rules'] ?? [])->pluck('field')->all())->not->toContain('address.country_id');

    $createResponse = $this->postJson('/api/v1/admin/speakers', [
        'name' => 'Admin API Created Speaker',
        'gender' => 'male',
        'status' => 'verified',
        'is_freelance' => false,
        'is_active' => true,
        'address' => [
            'country_id' => 132,
        ],
    ])->assertCreated();

    $speakerId = (string) $createResponse->json('data.record.id');
    $speaker = Speaker::query()->findOrFail($speakerId);
    $speakerRouteKey = (string) $speaker->getRouteKey();

    expect($speaker->name)->toBe('Admin API Created Speaker')
        ->and($speaker->slug)->toBe('admin-api-created-speaker-my')
        ->and($speaker->status)->toBe('verified')
        ->and($speaker->allow_public_event_submission)->toBeTrue();

    $this->putJson('/api/v1/admin/speakers/'.$speakerRouteKey, [
        'name' => 'Admin API Updated Speaker',
        'gender' => 'male',
        'honorific' => ['dato'],
        'pre_nominal' => ['dr', 'prof_madya'],
        'post_nominal' => ['BA', 'PhD', 'HONS'],
        'status' => 'verified',
        'is_freelance' => true,
        'job_title' => 'Imam',
        'is_active' => true,
        'allow_public_event_submission' => true,
        'address' => [
            'country_id' => 132,
        ],
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.name', 'Admin API Updated Speaker')
        ->assertJsonPath('data.record.attributes.slug', 'prof-madya-dato-dr-admin-api-updated-speaker-phd-ba-hons-my')
        ->assertJsonPath('data.record.attributes.job_title', 'Imam');
});

it('requires explicit country and still prohibits detailed address fields when creating speakers through the admin api', function () {
    $admin = adminApiUser('super_admin');

    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/speakers', [
        'name' => 'Admin API Missing Speaker Country',
        'gender' => 'male',
        'status' => 'verified',
        'is_freelance' => false,
        'is_active' => true,
        'address' => [],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'address.country_id',
        ]);

    $this->postJson('/api/v1/admin/speakers', [
        'name' => 'Admin API Invalid Speaker Address',
        'gender' => 'male',
        'status' => 'verified',
        'is_freelance' => false,
        'is_active' => true,
        'address' => [
            'country_id' => 132,
            'line1' => 'Alamat Lama',
            'google_maps_url' => 'https://maps.google.com/?q=1,1',
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'address.line1',
            'address.google_maps_url',
        ]);
});

it('returns fresh speaker address data on admin GET requests after updates', function () {
    $firstFixtures = ensureAdminApiSubdistrictFixtures();
    $secondFixtures = ensureAdminApiSubdistrictFixtures();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $createResponse = $this->postJson('/api/v1/admin/speakers', [
        'name' => 'Admin API Address Freshness Speaker',
        'gender' => 'male',
        'status' => 'verified',
        'is_freelance' => false,
        'is_active' => true,
        'address' => [
            'country_id' => $firstFixtures['country_id'],
            'state_id' => $firstFixtures['state_id'],
            'district_id' => $firstFixtures['district_id'],
        ],
    ])->assertCreated();

    $speakerId = (string) $createResponse->json('data.record.id');
    $speakerRouteKey = (string) Speaker::query()->findOrFail($speakerId)->getRouteKey();

    $this->getJson('/api/v1/admin/speakers/'.$speakerRouteKey)
        ->assertOk()
        ->assertJsonPath('data.record.attributes.address.country_id', $firstFixtures['country_id'])
        ->assertJsonMissingPath('data.record.attributes.address.line1')
        ->assertJsonMissingPath('data.record.attributes.address.google_maps_url')
        ->assertJsonPath('data.record.attributes.address.state_id', $firstFixtures['state_id'])
        ->assertJsonPath('data.record.attributes.address.district_id', $firstFixtures['district_id']);

    $this->putJson('/api/v1/admin/speakers/'.$speakerRouteKey, [
        'name' => 'Admin API Address Freshness Speaker',
        'gender' => 'male',
        'status' => 'verified',
        'is_freelance' => false,
        'is_active' => true,
        'address' => [
            'country_id' => $secondFixtures['country_id'],
            'state_id' => $secondFixtures['state_id'],
            'district_id' => $secondFixtures['district_id'],
        ],
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.address.country_id', $secondFixtures['country_id'])
        ->assertJsonMissingPath('data.record.attributes.address.line1')
        ->assertJsonMissingPath('data.record.attributes.address.google_maps_url')
        ->assertJsonPath('data.record.attributes.address.state_id', $secondFixtures['state_id'])
        ->assertJsonPath('data.record.attributes.address.district_id', $secondFixtures['district_id']);

    $this->getJson('/api/v1/admin/speakers/'.$speakerRouteKey)
        ->assertOk()
        ->assertJsonPath('data.record.attributes.address.country_id', $secondFixtures['country_id'])
        ->assertJsonMissingPath('data.record.attributes.address.line1')
        ->assertJsonMissingPath('data.record.attributes.address.google_maps_url')
        ->assertJsonPath('data.record.attributes.address.state_id', $secondFixtures['state_id'])
        ->assertJsonPath('data.record.attributes.address.district_id', $secondFixtures['district_id']);

    $this->getJson('/api/v1/admin/speakers?search=Admin%20API%20Address%20Freshness%20Speaker')
        ->assertOk()
        ->assertJsonPath('data.0.attributes.address.country_id', $secondFixtures['country_id'])
        ->assertJsonMissingPath('data.0.attributes.address.line1')
        ->assertJsonMissingPath('data.0.attributes.address.google_maps_url')
        ->assertJsonPath('data.0.attributes.address.state_id', $secondFixtures['state_id'])
        ->assertJsonPath('data.0.attributes.address.district_id', $secondFixtures['district_id']);
});

it('allows sparse venue address updates without resending the existing country through the admin api', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $createResponse = $this->postJson('/api/v1/admin/venues', [
        'name' => 'Admin API Sparse Venue Country',
        'type' => 'dewan',
        'status' => 'verified',
        'is_active' => true,
        'address' => [
            'country_id' => 132,
            'line1' => 'Alamat Asal',
        ],
    ])->assertCreated();

    $venueId = (string) $createResponse->json('data.record.id');
    $venueRouteKey = (string) Venue::query()->findOrFail($venueId)->getRouteKey();

    $this->putJson('/api/v1/admin/venues/'.$venueRouteKey, [
        'name' => 'Admin API Sparse Venue Country',
        'type' => 'dewan',
        'status' => 'verified',
        'is_active' => true,
        'address' => [
            'line1' => 'Alamat Terkini Tanpa Country',
        ],
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.address.country_id', 132)
        ->assertJsonPath('data.record.attributes.address.line1', 'Alamat Terkini Tanpa Country');

    expect(Venue::query()->findOrFail($venueId)->addressModel?->country_id)->toBe(132)
        ->and(Venue::query()->findOrFail($venueId)->addressModel?->line1)->toBe('Alamat Terkini Tanpa Country');
});

it('exposes admin institution write schema and can create and update institutions through the api', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $institutionSchema = $this->getJson('/api/v1/admin/institutions/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.schema.resource_key', 'institutions')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/institutions')
        ->json('data.schema');

    expect(collect($institutionSchema['fields'] ?? [])->pluck('name')->all())
        ->toContain('address.country_id')
        ->and(collect($institutionSchema['fields'] ?? [])->pluck('name')->all())->not->toContain('address.country_code', 'address.country_key')
        ->and(collect($institutionSchema['conditional_rules'] ?? [])->pluck('field')->all())->not->toContain('address.country_id');

    $createResponse = $this->postJson('/api/v1/admin/institutions', [
        'name' => 'Admin API Institution',
        'nickname' => 'API Surau',
        'type' => 'masjid',
        'status' => 'verified',
        'is_active' => true,
        'address' => [
            'country_id' => 132,
        ],
    ])->assertCreated();

    $institutionId = (string) $createResponse->json('data.record.id');
    $institution = Institution::query()->findOrFail($institutionId);
    $institutionRouteKey = (string) $institution->getRouteKey();

    expect($institution->display_name)->toBe('Admin API Institution (API Surau)')
        ->and($institution->status)->toBe('verified')
        ->and($institution->allow_public_event_submission)->toBeTrue();

    $this->putJson('/api/v1/admin/institutions/'.$institutionRouteKey, [
        'name' => 'Admin API Institution Updated',
        'nickname' => 'API Masjid',
        'type' => 'masjid',
        'status' => 'pending',
        'is_active' => true,
        'allow_public_event_submission' => true,
        'address' => [
            'country_id' => 132,
        ],
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.name', 'Admin API Institution Updated')
        ->assertJsonPath('data.record.attributes.nickname', 'API Masjid');
});

it('exposes admin venue write schema and can create and update venues through the api', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/venues/meta')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'venues')
        ->assertJsonPath('data.resource.write_support.schema', true)
        ->assertJsonPath('data.resource.write_support.store', true)
        ->assertJsonPath('data.resource.write_support.update', true)
        ->assertJsonPath('data.resource.api_routes.collection', '/api/v1/admin/venues')
        ->assertJsonPath('data.resource.api_routes.schema', '/api/v1/admin/venues/schema');

    $this->getJson('/api/v1/admin/venues/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.schema.resource_key', 'venues')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/venues')
        ->assertJsonPath('data.schema.content_type', 'multipart/form-data')
        ->assertJsonPath('data.schema.defaults.type', 'dewan')
        ->assertJsonPath('data.schema.defaults.is_active', true)
        ->assertJsonPath('data.schema.catalogs.0.field', 'address.country_id');

    $createResponse = $this->postJson('/api/v1/admin/venues', [
        'name' => 'Admin API Venue',
        'type' => 'dewan',
        'status' => 'verified',
        'is_active' => true,
        'facilities' => ['parking', 'oku'],
        'address' => [
            'country_id' => 132,
            'line1' => 'Dewan Serbaguna API',
        ],
        'contacts' => [
            [
                'category' => 'phone',
                'value' => '0312345678',
                'type' => 'main',
                'is_public' => true,
            ],
        ],
        'social_media' => [
            [
                'platform' => 'website',
                'url' => 'https://example.com/venues/admin-api-venue',
            ],
        ],
    ])->assertCreated();

    $venueId = (string) $createResponse->json('data.record.id');
    $venue = Venue::query()->with(['address', 'contacts', 'socialMedia'])->findOrFail($venueId);
    $venueRouteKey = (string) $venue->getRouteKey();

    expect($venue->name)->toBe('Admin API Venue')
        ->and($venue->slug)->toBe('admin-api-venue-my')
        ->and($venue->status)->toBe('verified')
        ->and($venue->is_active)->toBeTrue()
        ->and($venue->facilities)->toBe([
            'parking' => true,
            'oku' => true,
        ])
        ->and($venue->addressModel?->country_id)->toBe(132)
        ->and($venue->contacts)->toHaveCount(1)
        ->and($venue->contacts->first()?->value)->toBe('0312345678')
        ->and($venue->socialMedia)->toHaveCount(1)
        ->and($venue->socialMedia->first()?->platform)->toBe('website');

    $this->putJson('/api/v1/admin/venues/'.$venueRouteKey, [
        'name' => 'Admin API Venue Updated',
        'type' => 'auditorium',
        'status' => 'pending',
        'is_active' => false,
        'facilities' => ['women_section', 'ablution_area'],
        'address' => [
            'country_id' => 132,
            'line1' => 'Auditorium API Baharu',
        ],
        'contacts' => [
            [
                'category' => 'whatsapp',
                'value' => '60123456789',
                'type' => 'work',
                'is_public' => false,
            ],
        ],
        'social_media' => [
            [
                'platform' => 'facebook',
                'url' => 'https://facebook.com/admin-api-venue-updated',
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.name', 'Admin API Venue Updated')
        ->assertJsonPath('data.record.attributes.slug', 'admin-api-venue-updated-my')
        ->assertJsonPath('data.record.attributes.type', 'auditorium')
        ->assertJsonPath('data.record.attributes.is_active', false);

    $venue->refresh()->load(['address', 'contacts', 'socialMedia']);

    expect($venue->name)->toBe('Admin API Venue Updated')
        ->and($venue->slug)->toBe('admin-api-venue-updated-my')
        ->and($venue->getRawOriginal('type'))->toBe('auditorium')
        ->and($venue->status)->toBe('pending')
        ->and($venue->is_active)->toBeFalse()
        ->and($venue->facilities)->toBe([
            'women_section' => true,
            'ablution_area' => true,
        ])
        ->and($venue->addressModel?->line1)->toBe('Auditorium API Baharu')
        ->and($venue->contacts)->toHaveCount(1)
        ->and($venue->contacts->first()?->getRawOriginal('category'))->toBe('whatsapp')
        ->and($venue->socialMedia)->toHaveCount(1)
        ->and($venue->socialMedia->first()?->getRawOriginal('platform'))->toBe('facebook');
});

it('lists admin geography catalogs and exposes catalog metadata through admin write schemas', function () {
    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $fixtures = ensureAdminApiSubdistrictFixtures();

    $subdistrictId = DB::table('subdistricts')->insertGetId([
        'country_id' => $fixtures['country_id'],
        'state_id' => $fixtures['state_id'],
        'district_id' => $fixtures['district_id'],
        'name' => 'Admin API Catalog Subdistrict',
        'country_code' => 'MY',
    ]);

    $this->getJson('/api/v1/admin/catalogs/countries')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $fixtures['country_id'],
            'label' => 'Malaysia',
            'iso2' => 'MY',
            'key' => 'malaysia',
        ]);

    $this->getJson('/api/v1/admin/catalogs/states?country_id='.$fixtures['country_id'])
        ->assertOk()
        ->assertJsonFragment([
            'id' => $fixtures['state_id'],
        ]);

    $this->getJson('/api/v1/admin/catalogs/districts?state_id='.$fixtures['state_id'])
        ->assertOk()
        ->assertJsonFragment([
            'id' => $fixtures['district_id'],
        ]);

    $this->getJson('/api/v1/admin/catalogs/subdistricts?district_id='.$fixtures['district_id'])
        ->assertOk()
        ->assertJsonFragment([
            'id' => $subdistrictId,
            'label' => 'Admin API Catalog Subdistrict',
        ]);

    $institutionSchema = $this->getJson('/api/v1/admin/institutions/schema?operation=create')
        ->assertOk()
        ->json('data.schema.catalogs');

    $institutionCatalogs = collect(is_array($institutionSchema) ? $institutionSchema : [])->keyBy('field');

    expect($institutionCatalogs->get('address.country_id')['endpoint'] ?? null)->toBe('/api/v1/admin/catalogs/countries')
        ->and($institutionCatalogs->get('address.state_id')['query']['country_id'] ?? null)->toBe('{address.country_id}')
        ->and($institutionCatalogs->get('address.district_id')['query']['state_id'] ?? null)->toBe('{address.state_id}')
        ->and($institutionCatalogs->get('address.subdistrict_id')['query']['district_id'] ?? null)->toBe('{address.district_id}');

    $subdistrictSchema = $this->getJson('/api/v1/admin/subdistricts/schema?operation=create')
        ->assertOk()
        ->json('data.schema.catalogs');

    $subdistrictCatalogs = collect(is_array($subdistrictSchema) ? $subdistrictSchema : [])->keyBy('field');

    expect($subdistrictCatalogs->get('country_id')['endpoint'] ?? null)->toBe('/api/v1/admin/catalogs/countries')
        ->and($subdistrictCatalogs->get('state_id')['query']['country_id'] ?? null)->toBe('{country_id}')
        ->and($subdistrictCatalogs->get('district_id')['query']['state_id'] ?? null)->toBe('{state_id}');
});

it('exposes admin reference write schema and can create and update references through the api', function () {
    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/references/meta')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'references')
        ->assertJsonPath('data.resource.write_support.schema', true)
        ->assertJsonPath('data.resource.write_support.store', true)
        ->assertJsonPath('data.resource.write_support.update', true)
        ->assertJsonPath('data.resource.api_routes.collection', '/api/v1/admin/references')
        ->assertJsonPath('data.resource.api_routes.schema', '/api/v1/admin/references/schema');

    $this->getJson('/api/v1/admin/references/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.schema.resource_key', 'references')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/references')
        ->assertJsonPath('data.schema.content_type', 'multipart/form-data')
        ->assertJsonPath('data.schema.defaults.type', 'book');

    $createResponse = $this->postJson('/api/v1/admin/references', [
        'title' => 'Admin API Reference',
        'author' => 'Admin API Author',
        'type' => 'book',
        'publication_year' => '2024',
        'publisher' => 'Admin API Press',
        'description' => 'Admin API reference description.',
        'is_canonical' => true,
        'status' => 'verified',
        'is_active' => true,
        'social_media' => [
            [
                'platform' => 'website',
                'url' => 'https://example.com/references/admin-api-reference',
            ],
        ],
    ])->assertCreated();

    $referenceId = (string) $createResponse->json('data.record.id');
    $reference = Reference::query()->with('socialMedia')->findOrFail($referenceId);
    $referenceRouteKey = (string) $reference->getRouteKey();

    expect($reference->title)->toBe('Admin API Reference')
        ->and($reference->slug)->toBe('admin-api-reference')
        ->and($reference->is_canonical)->toBeTrue()
        ->and($reference->status)->toBe('verified')
        ->and($reference->is_active)->toBeTrue()
        ->and($reference->socialMedia)->toHaveCount(1)
        ->and($reference->socialMedia->first()?->platform)->toBe('website');

    $this->getJson('/api/v1/admin/references/'.$referenceRouteKey)
        ->assertOk()
        ->assertJsonPath('data.record.route_key', $referenceRouteKey)
        ->assertJsonPath('data.record.attributes.slug', 'admin-api-reference');

    $this->getJson('/api/v1/admin/references/'.$referenceId)->assertNotFound();

    $this->getJson('/api/v1/admin/references/schema?operation=update&recordKey='.$referenceRouteKey)
        ->assertOk()
        ->assertJsonPath('data.schema.method', 'PUT')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/references/'.$referenceRouteKey)
        ->assertJsonPath('data.schema.defaults.title', 'Admin API Reference');

    $this->putJson('/api/v1/admin/references/'.$referenceRouteKey, [
        'title' => 'Admin API Reference Updated',
        'author' => 'Admin API Editor',
        'type' => 'article',
        'publication_year' => null,
        'publisher' => 'Admin API Review',
        'description' => 'Updated admin API reference description.',
        'is_canonical' => false,
        'status' => 'pending',
        'is_active' => false,
        'social_media' => [
            [
                'platform' => 'youtube',
                'url' => 'https://youtube.com/watch?v=admin-api-reference-updated',
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.title', 'Admin API Reference Updated')
        ->assertJsonPath('data.record.attributes.slug', 'admin-api-reference-updated')
        ->assertJsonPath('data.record.attributes.type', 'article');

    $reference->refresh()->load('socialMedia');

    expect($reference->title)->toBe('Admin API Reference Updated')
        ->and($reference->slug)->toBe('admin-api-reference-updated')
        ->and($reference->type)->toBe('article')
        ->and($reference->publication_year)->toBeNull()
        ->and($reference->publisher)->toBe('Admin API Review')
        ->and($reference->is_canonical)->toBeFalse()
        ->and($reference->status)->toBe('pending')
        ->and($reference->is_active)->toBeFalse()
        ->and($reference->socialMedia)->toHaveCount(1)
        ->and($reference->socialMedia->first()?->platform)->toBe('youtube');

    $this->getJson('/api/v1/admin/references/'.$reference->getRouteKey())
        ->assertOk()
        ->assertJsonPath('data.record.route_key', 'admin-api-reference-updated')
        ->assertJsonPath('data.record.attributes.slug', 'admin-api-reference-updated');
});

it('exposes admin subdistrict write schema and can create and update subdistricts through the api', function () {
    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $fixtures = ensureAdminApiSubdistrictFixtures();

    $this->getJson('/api/v1/admin/subdistricts/meta')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'subdistricts')
        ->assertJsonPath('data.resource.write_support.schema', true)
        ->assertJsonPath('data.resource.write_support.store', true)
        ->assertJsonPath('data.resource.write_support.update', true)
        ->assertJsonPath('data.resource.api_routes.collection', '/api/v1/admin/subdistricts')
        ->assertJsonPath('data.resource.api_routes.schema', '/api/v1/admin/subdistricts/schema');

    $this->getJson('/api/v1/admin/subdistricts/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.schema.resource_key', 'subdistricts')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/subdistricts')
        ->assertJsonPath('data.schema.content_type', 'application/json')
        ->assertJsonPath('data.schema.conditional_rules.0.field', 'district_id');

    $createResponse = $this->postJson('/api/v1/admin/subdistricts', [
        'country_id' => $fixtures['country_id'],
        'state_id' => $fixtures['federal_state_id'],
        'district_id' => null,
        'name' => 'Admin API Federal Territory Subdistrict',
    ])->assertCreated();

    $subdistrictId = (string) $createResponse->json('data.record.id');
    $subdistrict = Subdistrict::query()->findOrFail($subdistrictId);
    $subdistrictRouteKey = (string) $subdistrict->getRouteKey();

    expect((int) $subdistrict->country_id)->toBe($fixtures['country_id'])
        ->and((int) $subdistrict->state_id)->toBe($fixtures['federal_state_id'])
        ->and($subdistrict->district_id)->toBeNull()
        ->and($subdistrict->country_code)->toBe('MY');

    $this->getJson('/api/v1/admin/subdistricts/schema?operation=update&recordKey='.$subdistrictRouteKey)
        ->assertOk()
        ->assertJsonPath('data.schema.method', 'PUT')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/subdistricts/'.$subdistrictRouteKey)
        ->assertJsonPath('data.schema.defaults.country_id', $fixtures['country_id'])
        ->assertJsonPath('data.schema.defaults.state_id', $fixtures['federal_state_id'])
        ->assertJsonPath('data.schema.defaults.district_id', null)
        ->assertJsonPath('data.schema.defaults.name', 'Admin API Federal Territory Subdistrict');

    $this->putJson('/api/v1/admin/subdistricts/'.$subdistrictRouteKey, [
        'country_id' => $fixtures['country_id'],
        'state_id' => $fixtures['state_id'],
        'district_id' => $fixtures['district_id'],
        'name' => 'Admin API Updated Subdistrict',
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.name', 'Admin API Updated Subdistrict')
        ->assertJsonPath('data.record.attributes.district_id', $fixtures['district_id']);

    $subdistrict->refresh();

    expect((int) $subdistrict->state_id)->toBe($fixtures['state_id'])
        ->and((int) $subdistrict->district_id)->toBe($fixtures['district_id'])
        ->and($subdistrict->name)->toBe('Admin API Updated Subdistrict');
});

it('clamps admin collection per_page values to the supported maximum', function () {
    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    Speaker::factory()->count(110)->create();

    $this->getJson('/api/v1/admin/speakers?per_page=500')
        ->assertOk()
        ->assertJsonPath('meta.pagination.per_page', 100)
        ->assertJsonCount(100, 'data');
});

it('requires district_id for non-federal-territory subdistrict writes', function () {
    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $fixtures = ensureAdminApiSubdistrictFixtures();

    $this->postJson('/api/v1/admin/subdistricts', [
        'country_id' => $fixtures['country_id'],
        'state_id' => $fixtures['state_id'],
        'district_id' => null,
        'name' => 'Admin API Invalid Subdistrict',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['district_id']);
});

it('exposes admin event write schema and can create and update events through the api', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $reference = Reference::factory()->verified()->create();
    $series = Series::factory()->create();
    $domainTag = Tag::factory()->domain()->verified()->create();
    $disciplineTag = Tag::factory()->discipline()->verified()->create();
    $sourceTag = Tag::factory()->source()->verified()->create();

    $this->getJson('/api/v1/admin/events/meta')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'events')
        ->assertJsonPath('data.resource.write_support.schema', true)
        ->assertJsonPath('data.resource.write_support.store', true)
        ->assertJsonPath('data.resource.write_support.update', true)
        ->assertJsonPath('data.resource.api_routes.collection', '/api/v1/admin/events')
        ->assertJsonPath('data.resource.api_routes.schema', '/api/v1/admin/events/schema');

    $this->getJson('/api/v1/admin/events/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.schema.resource_key', 'events')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/events')
        ->assertJsonPath('data.schema.content_type', 'multipart/form-data')
        ->assertJsonPath('data.schema.defaults.live_url', null);

    $createResponse = $this->postJson('/api/v1/admin/events', adminApiEventPayload([
        'institution' => $institution,
        'speaker' => $speaker,
        'reference' => $reference,
        'series' => $series,
        'domain_tag' => $domainTag,
        'discipline_tag' => $disciplineTag,
    ]))->assertCreated();

    $eventId = (string) $createResponse->json('data.record.id');
    $event = Event::query()
        ->with(['settings', 'references', 'series', 'tags', 'keyPeople'])
        ->findOrFail($eventId);
    $eventRouteKey = (string) $event->getRouteKey();

    expect($event->title)->toBe('Admin API Event Created')
        ->and($event->live_url)->toBeNull()
        ->and($event->organizer_type)->toBe(Institution::class)
        ->and($event->organizer_id)->toBe($institution->getKey())
        ->and($event->starts_at?->copy()->timezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i'))->toBe('2026-05-20 20:00')
        ->and($event->settings?->registration_required)->toBeTrue()
        ->and($event->settings?->registration_mode)->toBe(RegistrationMode::Event)
        ->and($event->references->pluck('id')->all())->toContain($reference->getKey())
        ->and($event->series->pluck('id')->all())->toContain($series->getKey())
        ->and($event->tags->pluck('id')->all())->toContain($domainTag->getKey(), $disciplineTag->getKey())
        ->and($event->keyPeople)->toHaveCount(2);

    $this->putJson('/api/v1/admin/events/'.$eventRouteKey, adminApiEventPayload([
        'institution' => $institution,
        'speaker' => $speaker,
        'reference' => $reference,
        'series' => $series,
        'domain_tag' => $domainTag,
        'discipline_tag' => $disciplineTag,
    ], [
        'title' => 'Admin API Event Updated',
        'event_date' => '2026-06-01',
        'prayer_time' => EventPrayerTime::SelepasMaghrib->value,
        'custom_time' => null,
        'end_time' => '22:30',
        'live_url' => 'https://youtube.com/watch?v=admin-api-event-live',
        'organizer_type' => Speaker::class,
        'organizer_id' => $speaker->getKey(),
        'institution_id' => null,
        'references' => [],
        'series' => [],
        'domain_tags' => [],
        'discipline_tags' => [],
        'source_tags' => [(string) $sourceTag->getKey()],
        'speakers' => [],
        'other_key_people' => [],
        'registration_required' => false,
    ]))->assertOk()
        ->assertJsonPath('data.record.attributes.title', 'Admin API Event Updated')
        ->assertJsonPath('data.record.attributes.live_url', 'https://youtube.com/watch?v=admin-api-event-live');

    $event->refresh()->load(['settings', 'references', 'series', 'tags', 'keyPeople']);

    expect($event->title)->toBe('Admin API Event Updated')
        ->and($event->live_url)->toBe('https://youtube.com/watch?v=admin-api-event-live')
        ->and($event->organizer_type)->toBe(Speaker::class)
        ->and($event->organizer_id)->toBe($speaker->getKey())
        ->and($event->starts_at?->copy()->timezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i'))->toBe('2026-06-01 20:00')
        ->and($event->settings?->registration_required)->toBeFalse()
        ->and($event->references)->toHaveCount(0)
        ->and($event->series)->toHaveCount(0)
        ->and($event->tags->pluck('id')->all())->toContain($sourceTag->getKey())
        ->and($event->tags->pluck('id')->all())->not->toContain($domainTag->getKey(), $disciplineTag->getKey())
        ->and($event->keyPeople)->toHaveCount(0)
        ->and($event->slug)->toContain($speaker->slug);
});

it('rejects admin event writes that omit required speakers for speaker-led event types', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $reference = Reference::factory()->verified()->create();
    $series = Series::factory()->create();
    $domainTag = Tag::factory()->domain()->verified()->create();
    $disciplineTag = Tag::factory()->discipline()->verified()->create();

    $this->postJson('/api/v1/admin/events', adminApiEventPayload([
        'institution' => $institution,
        'speaker' => $speaker,
        'reference' => $reference,
        'series' => $series,
        'domain_tag' => $domainTag,
        'discipline_tag' => $disciplineTag,
    ], [
        'event_type' => [EventType::KuliahCeramah->value],
        'speakers' => [],
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['speakers']);
});

it('rejects admin event writes with organizer ids that do not match the organizer type', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $reference = Reference::factory()->verified()->create();
    $series = Series::factory()->create();
    $domainTag = Tag::factory()->domain()->verified()->create();
    $disciplineTag = Tag::factory()->discipline()->verified()->create();

    $this->postJson('/api/v1/admin/events', adminApiEventPayload([
        'institution' => $institution,
        'speaker' => $speaker,
        'reference' => $reference,
        'series' => $series,
        'domain_tag' => $domainTag,
        'discipline_tag' => $disciplineTag,
    ], [
        'organizer_type' => Institution::class,
        'organizer_id' => (string) $speaker->getKey(),
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['organizer_id']);
});

it('rejects admin event writes with conflicting location selections', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $otherInstitution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $reference = Reference::factory()->verified()->create();
    $series = Series::factory()->create();
    $domainTag = Tag::factory()->domain()->verified()->create();
    $disciplineTag = Tag::factory()->discipline()->verified()->create();
    $venue = Venue::factory()->create();
    $space = Space::factory()->create();
    $otherInstitution->spaces()->attach($space);

    $this->postJson('/api/v1/admin/events', adminApiEventPayload([
        'institution' => $institution,
        'speaker' => $speaker,
        'reference' => $reference,
        'series' => $series,
        'domain_tag' => $domainTag,
        'discipline_tag' => $disciplineTag,
    ], [
        'venue_id' => (string) $venue->getKey(),
        'space_id' => (string) $space->getKey(),
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['institution_id', 'venue_id', 'space_id']);
});

function ensureAdminApiMalaysiaCountryExists(): int
{
    $malaysiaId = DB::table('countries')->where('id', 132)->value('id');

    if (is_int($malaysiaId)) {
        return $malaysiaId;
    }

    return DB::table('countries')->insertGetId([
        'id' => 132,
        'iso2' => 'MY',
        'name' => 'Malaysia',
        'status' => 1,
        'phone_code' => '60',
        'iso3' => 'MYS',
        'region' => 'Asia',
        'subregion' => 'South-Eastern Asia',
    ]);
}

/**
 * @return array{country_id: int, state_id: int, district_id: int, federal_state_id: int}
 */
function ensureAdminApiSubdistrictFixtures(): array
{
    $countryId = ensureAdminApiMalaysiaCountryExists();
    $suffix = Str::lower(Str::random(8));

    $stateId = DB::table('states')->insertGetId([
        'country_id' => $countryId,
        'name' => 'Admin API Negeri '.$suffix,
        'country_code' => 'MY',
    ]);

    $districtId = DB::table('districts')->insertGetId([
        'country_id' => $countryId,
        'state_id' => $stateId,
        'name' => 'Admin API Daerah '.$suffix,
        'country_code' => 'MY',
    ]);

    $federalStateId = DB::table('states')
        ->where('country_id', $countryId)
        ->where('name', 'Kuala Lumpur')
        ->value('id');

    if (! is_int($federalStateId)) {
        $federalStateId = DB::table('states')->insertGetId([
            'country_id' => $countryId,
            'name' => 'Kuala Lumpur',
            'country_code' => 'MY',
        ]);
    }

    FederalTerritoryLocation::flushStateIdCache();

    return [
        'country_id' => $countryId,
        'state_id' => $stateId,
        'district_id' => $districtId,
        'federal_state_id' => $federalStateId,
    ];
}

function adminApiUser(string $role): User
{
    setPermissionsTeamId(null);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

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
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/**
 * @param  array{
 *     institution: Institution,
 *     speaker: Speaker,
 *     reference: Reference,
 *     series: Series,
 *     domain_tag: Tag,
 *     discipline_tag: Tag
 * }  $fixtures
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function adminApiEventPayload(array $fixtures, array $overrides = []): array
{
    return array_replace([
        'title' => 'Admin API Event Created',
        'event_date' => '2026-05-20',
        'prayer_time' => EventPrayerTime::LainWaktu->value,
        'custom_time' => '20:00',
        'end_time' => '22:00',
        'timezone' => 'Asia/Kuala_Lumpur',
        'event_format' => EventFormat::Hybrid->value,
        'visibility' => EventVisibility::Public->value,
        'event_url' => 'https://example.com/events/admin-api-event-created',
        'live_url' => null,
        'recording_url' => 'https://example.com/recordings/admin-api-event-created',
        'gender' => EventGenderRestriction::All->value,
        'age_group' => [EventAgeGroup::AllAges->value],
        'children_allowed' => true,
        'is_muslim_only' => true,
        'event_type' => [EventType::Other->value],
        'domain_tags' => [(string) $fixtures['domain_tag']->getKey()],
        'discipline_tags' => [(string) $fixtures['discipline_tag']->getKey()],
        'source_tags' => [],
        'issue_tags' => [],
        'references' => [(string) $fixtures['reference']->getKey()],
        'organizer_type' => Institution::class,
        'organizer_id' => (string) $fixtures['institution']->getKey(),
        'institution_id' => (string) $fixtures['institution']->getKey(),
        'series' => [(string) $fixtures['series']->getKey()],
        'speakers' => [(string) $fixtures['speaker']->getKey()],
        'other_key_people' => [
            [
                'role' => 'moderator',
                'name' => 'Admin API Moderator',
                'is_public' => true,
                'notes' => 'Will host the session.',
            ],
        ],
        'registration_required' => true,
        'registration_mode' => RegistrationMode::Event->value,
        'is_active' => true,
    ], $overrides);
}
