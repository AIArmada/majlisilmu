<?php

use App\Models\Institution;
use App\Models\Speaker;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

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

    $resourceKeys = collect($response->json('data.resources'))->pluck('key')->all();

    expect($resourceKeys)->toContain('speakers', 'events', 'institutions');
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

it('returns admin speaker resource metadata and records', function () {
    $admin = adminApiUser('super_admin');
    $speaker = Speaker::factory()->create([
        'name' => 'Admin API Speaker',
    ]);

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

    $this->getJson('/api/v1/admin/speakers/'.$speaker->getKey())
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'speakers')
        ->assertJsonPath('data.record.id', $speaker->getKey())
        ->assertJsonPath('data.record.attributes.name', 'Admin API Speaker')
        ->assertJsonPath('data.record.abilities.view', true);
});

it('exposes admin speaker write schema and can create and update speakers through the api', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/speakers/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.schema.resource_key', 'speakers')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.slug_behavior', 'auto_managed')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/speakers');

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

    expect($speaker->name)->toBe('Admin API Created Speaker')
        ->and($speaker->status)->toBe('verified')
        ->and($speaker->allow_public_event_submission)->toBeTrue();

    $this->putJson('/api/v1/admin/speakers/'.$speakerId, [
        'name' => 'Admin API Updated Speaker',
        'gender' => 'male',
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
        ->assertJsonPath('data.record.attributes.job_title', 'Imam');
});

it('requires address.country_id when creating speakers through the admin api', function () {
    $admin = adminApiUser('super_admin');

    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/speakers', [
        'name' => 'Admin API Missing Country Speaker',
        'gender' => 'male',
        'status' => 'verified',
        'is_freelance' => false,
        'is_active' => true,
        'address' => [],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['address.country_id']);
});

it('returns fresh speaker address data on admin GET requests after updates', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $createResponse = $this->postJson('/api/v1/admin/speakers', [
        'name' => 'Admin API Address Freshness Speaker',
        'gender' => 'male',
        'status' => 'verified',
        'is_freelance' => false,
        'is_active' => true,
        'address' => [
            'country_id' => 132,
            'line1' => 'Alamat Asal',
        ],
    ])->assertCreated();

    $speakerId = (string) $createResponse->json('data.record.id');

    $this->getJson('/api/v1/admin/speakers/'.$speakerId)
        ->assertOk()
        ->assertJsonPath('data.record.attributes.address.country_id', 132)
        ->assertJsonPath('data.record.attributes.address.line1', 'Alamat Asal');

    $this->putJson('/api/v1/admin/speakers/'.$speakerId, [
        'name' => 'Admin API Address Freshness Speaker',
        'gender' => 'male',
        'status' => 'verified',
        'is_freelance' => false,
        'is_active' => true,
        'address' => [
            'country_id' => 132,
            'line1' => 'Alamat Dikemas Kini',
        ],
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.address.country_id', 132)
        ->assertJsonPath('data.record.attributes.address.line1', 'Alamat Dikemas Kini');

    $this->getJson('/api/v1/admin/speakers/'.$speakerId)
        ->assertOk()
        ->assertJsonPath('data.record.attributes.address.country_id', 132)
        ->assertJsonPath('data.record.attributes.address.line1', 'Alamat Dikemas Kini');

    $this->getJson('/api/v1/admin/speakers?search=Admin%20API%20Address%20Freshness%20Speaker')
        ->assertOk()
        ->assertJsonPath('data.0.attributes.address.country_id', 132)
        ->assertJsonPath('data.0.attributes.address.line1', 'Alamat Dikemas Kini');
});

it('exposes admin institution write schema and can create and update institutions through the api', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/institutions/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.schema.resource_key', 'institutions')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/institutions');

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

    expect($institution->display_name)->toBe('Admin API Institution (API Surau)')
        ->and($institution->status)->toBe('verified')
        ->and($institution->allow_public_event_submission)->toBeTrue();

    $this->putJson('/api/v1/admin/institutions/'.$institutionId, [
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

function adminApiUser(string $role): User
{
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
