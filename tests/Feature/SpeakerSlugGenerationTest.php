<?php

use App\Actions\Contributions\ApproveContributionRequestAction;
use App\Actions\Speakers\GenerateSpeakerSlugAction;
use App\Enums\ContributionRequestStatus;
use App\Enums\ContributionRequestType;
use App\Enums\ContributionSubjectType;
use App\Filament\Resources\Speakers\Pages\CreateSpeaker;
use App\Forms\SpeakerFormSchema;
use App\Jobs\BackfillSpeakerSlugs;
use App\Models\ContributionRequest;
use App\Models\Country;
use App\Models\Speaker;
use App\Models\User;
use App\Services\ContributionEntityMutationService;
use App\Support\Cache\PublicListingsCache;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('generates country-based slugs for speaker quick-create flows', function () {
    $country = createSpeakerSlugCountry();

    $speakerId = SpeakerFormSchema::createOptionUsing([
        'name' => 'Ustaz Ahmad Fauzi',
        'gender' => 'male',
        'country_id' => (string) $country->getKey(),
    ]);

    $speaker = Speaker::query()
        ->with('address')
        ->findOrFail($speakerId);

    expect($speaker->slug)->toBe('ustaz-ahmad-fauzi-my')
        ->and($speaker->addressModel?->country_id)->toBe((int) $country->getKey());
});

it('adds duplicate numbering only when the same speaker name reuses the same country suffix', function () {
    $proposer = User::factory()->create();
    $malaysia = createSpeakerSlugCountry();
    $singapore = createSpeakerSlugCountry(
        countryName: 'Singapore',
        countryIso2: 'SG',
        countryIso3: 'SGP',
        countryId: 702,
        phoneCode: '65',
    );

    $first = app(ContributionEntityMutationService::class)->createSpeaker([
        'name' => 'Ustaz Ahmad Fauzi',
        'gender' => 'male',
        'country_id' => (string) $malaysia->getKey(),
    ], $proposer);

    $second = app(ContributionEntityMutationService::class)->createSpeaker([
        'name' => 'Ustaz Ahmad Fauzi',
        'gender' => 'male',
        'country_id' => (string) $malaysia->getKey(),
    ], $proposer);

    $third = app(ContributionEntityMutationService::class)->createSpeaker([
        'name' => 'Ustaz Ahmad Fauzi',
        'gender' => 'male',
        'country_id' => (string) $singapore->getKey(),
    ], $proposer);

    expect($first->slug)->toBe('ustaz-ahmad-fauzi-my')
        ->and($first->addressModel?->country_id)->toBe((int) $malaysia->getKey())
        ->and($second->slug)->toBe('ustaz-ahmad-fauzi-2-my')
        ->and($third->slug)->toBe('ustaz-ahmad-fauzi-sg');
});

it('keeps speaker slugs unique when a literal numbered name already occupies the expected duplicate slot', function () {
    $proposer = User::factory()->create();
    $country = createSpeakerSlugCountry();

    $first = app(ContributionEntityMutationService::class)->createSpeaker([
        'name' => 'Ustaz Example',
        'gender' => 'male',
        'country_id' => (string) $country->getKey(),
    ], $proposer);

    $numberedName = app(ContributionEntityMutationService::class)->createSpeaker([
        'name' => 'Ustaz Example 2',
        'gender' => 'male',
        'country_id' => (string) $country->getKey(),
    ], $proposer);

    $duplicate = app(ContributionEntityMutationService::class)->createSpeaker([
        'name' => 'Ustaz Example',
        'gender' => 'male',
        'country_id' => (string) $country->getKey(),
    ], $proposer);

    expect($first->slug)->toBe('ustaz-example-my')
        ->and($numberedName->slug)->toBe('ustaz-example-2-my')
        ->and($duplicate->slug)->toBe('ustaz-example-3-my');
});

it('uses the generated country slug when admins create speakers in filament', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $administrator = User::factory()->create();
    $administrator->assignRole('super_admin');
    $country = createSpeakerSlugCountry();

    Livewire::actingAs($administrator)
        ->test(CreateSpeaker::class)
        ->fillForm([
            'name' => 'Ustaz Ahmad Fauzi',
            'gender' => 'male',
            'honorific' => [],
            'pre_nominal' => [],
            'post_nominal' => [],
            'qualifications' => [],
            'languages' => [],
            'contacts' => [],
            'socialMedia' => [],
            'slug' => 'temporary-speaker-slug',
            'status' => 'verified',
            'is_active' => true,
            'address' => [
                'country_id' => (string) $country->getKey(),
            ],
        ])
        ->call('create')
        ->assertHasNoErrors();

    $speaker = Speaker::query()
        ->where('name', 'Ustaz Ahmad Fauzi')
        ->firstOrFail();

    expect($speaker->slug)->toBe('ustaz-ahmad-fauzi-my');
});

it('uses the submitted address country when approving unstaged speaker create requests', function () {
    $country = createSpeakerSlugCountry();
    $proposer = User::factory()->create();
    $reviewer = User::factory()->create();

    $request = ContributionRequest::factory()->create([
        'type' => ContributionRequestType::Create,
        'subject_type' => ContributionSubjectType::Speaker,
        'proposer_id' => $proposer->id,
        'status' => ContributionRequestStatus::Pending,
        'proposed_data' => [
            'name' => 'Ustaz Ahmad Approval',
            'gender' => 'male',
            'address' => [
                'country_id' => (string) $country->getKey(),
            ],
        ],
        'original_data' => null,
    ]);

    $approvedRequest = app(ApproveContributionRequestAction::class)->handle($request, $reviewer, 'Approved.');
    $speaker = Speaker::query()
        ->with('address')
        ->findOrFail($approvedRequest->entity_id);

    expect($speaker->slug)->toBe('ustaz-ahmad-approval-my')
        ->and($speaker->addressModel?->country_id)->toBe((int) $country->getKey());
});

it('backfills existing speaker slugs through the queued job logic', function () {
    $country = createSpeakerSlugCountry();

    $first = createSpeakerForSlugBackfill(
        id: '00000000-0000-0000-0000-000000000011',
        name: 'Ustaz Ahmad Fauzi',
        slug: 'legacy-random-1',
        country: $country,
    );

    $second = createSpeakerForSlugBackfill(
        id: '00000000-0000-0000-0000-000000000012',
        name: 'Ustaz Ahmad Fauzi',
        slug: 'legacy-random-2',
        country: $country,
    );

    app(BackfillSpeakerSlugs::class)->handle(
        app(GenerateSpeakerSlugAction::class),
        app(PublicListingsCache::class),
    );

    expect($first->fresh()?->slug)->toBe('ustaz-ahmad-fauzi-my')
        ->and($second->fresh()?->slug)->toBe('ustaz-ahmad-fauzi-2-my');
});

it('queues the speaker slug backfill command', function () {
    Queue::fake();

    $this->artisan('speakers:queue-slug-backfill')
        ->expectsOutput('Queued speaker slug backfill job.')
        ->assertSuccessful();

    Queue::assertPushed(BackfillSpeakerSlugs::class);
});

it('skips the country suffix when speaker country data is missing', function () {
    $country = createSpeakerSlugCountry();
    $generator = app(GenerateSpeakerSlugAction::class);

    expect($generator->handle('Ustaz Tanpa Negara'))->toBe('ustaz-tanpa-negara')
        ->and($generator->handle('Ustaz Malaysia', [
            'country_id' => (string) $country->getKey(),
        ]))->toBe('ustaz-malaysia-my')
        ->and($generator->handle('Ustaz Singapura', [
            'country_code' => 'SG',
        ]))->toBe('ustaz-singapura-sg');
});

function createSpeakerSlugCountry(
    string $countryName = 'Malaysia',
    string $countryIso2 = 'MY',
    string $countryIso3 = 'MYS',
    int $countryId = 132,
    string $phoneCode = '60',
): Country {
    $country = Country::query()->find($countryId);

    if ($country instanceof Country) {
        return $country;
    }

    $country = new Country;
    $country->forceFill([
        'id' => $countryId,
        'name' => $countryName,
        'iso2' => $countryIso2,
        'iso3' => $countryIso3,
        'phone_code' => $phoneCode,
        'region' => 'Asia',
        'subregion' => 'South-Eastern Asia',
        'status' => 1,
    ]);
    $country->save();

    return $country;
}

function createSpeakerForSlugBackfill(string $id, string $name, string $slug, Country $country): Speaker
{
    $speaker = Speaker::unguarded(fn () => Speaker::query()->create([
        'id' => $id,
        'name' => $name,
        'gender' => 'male',
        'slug' => $slug,
        'status' => 'verified',
        'is_active' => true,
    ]));

    $speaker->address()->create([
        'type' => 'main',
        'country_id' => (int) $country->getKey(),
    ]);

    return $speaker->fresh(['address']) ?? $speaker;
}
