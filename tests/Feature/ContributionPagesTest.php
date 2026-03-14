<?php

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\Signals\Models\SignalEvent;
use App\Enums\ContributionRequestStatus;
use App\Enums\ContributionRequestType;
use App\Enums\ContributionSubjectType;
use App\Livewire\Pages\Contributions\Index as ContributionsIndex;
use App\Livewire\Pages\Contributions\SuggestUpdate;
use App\Livewire\Pages\Reports\Create as CreateReportPage;
use App\Models\ContributionRequest;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use App\Models\User;
use App\Support\Authz\MemberRoleScopes;
use App\Support\Authz\ScopedMemberRoleSeeder;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    setPermissionsTeamId(null);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function assignInstitutionOwner(User $user, Institution $institution): void
{
    app(ScopedMemberRoleSeeder::class)->ensureForInstitution();
    $institution->members()->syncWithoutDetaching([$user->id]);

    Authz::withScope(app(MemberRoleScopes::class)->institution(), function () use ($user): void {
        $user->syncRoles(['owner']);
    }, $user);
}

it('renders the dedicated institution contribution page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get(route('contributions.submit-institution'))
        ->assertOk()
        ->assertSee(__('Add a New Institution'));
});

it('renders the dedicated speaker contribution page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get(route('contributions.submit-speaker'))
        ->assertOk()
        ->assertSee(__('Add a New Speaker'));
});

it('applies direct institution edits for owner maintainers from the suggest update page', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'name' => 'Masjid Lama',
        'status' => 'verified',
    ]);
    $institution->contacts()->delete();

    assignInstitutionOwner($user, $institution);
    $this->actingAs($user);

    Livewire::test(SuggestUpdate::class, [
        'subjectType' => 'institution',
        'subjectId' => $institution->slug,
    ])
        ->set('data.name', 'Masjid Baru')
        ->call('submit')
        ->assertHasNoErrors();

    expect($institution->fresh()->name)->toBe('Masjid Baru')
        ->and(ContributionRequest::query()->count())->toBe(0);
});

it('creates pending update requests for non-maintainer suggestions', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'description' => 'Old description',
        'status' => 'verified',
    ]);
    $institution->contacts()->delete();

    $this->actingAs($user);

    Livewire::test(SuggestUpdate::class, [
        'subjectType' => 'institution',
        'subjectId' => $institution->slug,
    ])
        ->set('data.description', 'Updated community description')
        ->set('data.address.line1', 'No. 8, Jalan Baru')
        ->set('data.proposer_note', 'This institution recently expanded its programs.')
        ->call('submit')
        ->assertHasNoErrors();

    $request = ContributionRequest::query()->latest('created_at')->first();

    expect($request)->not->toBeNull()
        ->and($request?->type)->toBe(ContributionRequestType::Update)
        ->and($request?->entity_id)->toBe($institution->id)
        ->and($request?->status)->toBe(ContributionRequestStatus::Pending)
        ->and($request?->proposed_data['description'] ?? null)->toBe('Updated community description')
        ->and(data_get($request?->proposed_data, 'address.line1'))->toBe('No. 8, Jalan Baru');
});

it('lets maintainers approve pending update requests from the contributions inbox', function () {
    $owner = User::factory()->create();
    $proposer = User::factory()->create();
    $institution = Institution::factory()->create([
        'description' => 'Before approval',
        'status' => 'verified',
    ]);
    $request = ContributionRequest::factory()->create([
        'type' => ContributionRequestType::Update,
        'subject_type' => ContributionSubjectType::Institution,
        'entity_type' => $institution->getMorphClass(),
        'entity_id' => $institution->id,
        'proposer_id' => $proposer->id,
        'status' => ContributionRequestStatus::Pending,
        'proposed_data' => [
            'description' => 'After approval',
        ],
        'original_data' => [
            'description' => 'Before approval',
        ],
    ]);

    assignInstitutionOwner($owner, $institution);
    $this->actingAs($owner);

    Livewire::test(ContributionsIndex::class)
        ->set("reviewNotes.{$request->id}", 'Looks accurate.')
        ->call('approve', $request->id);

    expect($request->fresh()->status)->toBe(ContributionRequestStatus::Approved)
        ->and($institution->fresh()->description)->toBe('After approval');
});

it('lets maintainers reject pending update requests from the contributions inbox', function () {
    $owner = User::factory()->create();
    $proposer = User::factory()->create();
    $institution = Institution::factory()->create([
        'description' => 'Keep this description',
        'status' => 'verified',
    ]);
    $institution->contacts()->delete();

    assignInstitutionOwner($owner, $institution);

    $this->actingAs($proposer);

    Livewire::test(SuggestUpdate::class, [
        'subjectType' => 'institution',
        'subjectId' => $institution->slug,
    ])
        ->set('data.description', 'Rejected description')
        ->set('data.proposer_note', 'Please change this.')
        ->call('submit')
        ->assertHasNoErrors();

    $request = ContributionRequest::query()->latest('created_at')->firstOrFail();

    $this->actingAs($owner);

    Livewire::test(ContributionsIndex::class)
        ->set("reviewNotes.{$request->id}", 'Need stronger evidence.')
        ->set("rejectionReasons.{$request->id}", 'needs_more_evidence')
        ->call('reject', $request->id);

    expect($request->fresh()->status)->toBe(ContributionRequestStatus::Rejected)
        ->and($request->fresh()->reviewer_note)->toBe('Need stronger evidence.')
        ->and($request->fresh()->reason_code)->toBe('needs_more_evidence')
        ->and($institution->fresh()->description)->toBe('Keep this description');
});

it('stores reference reports from the public report page', function () {
    $user = User::factory()->create();
    $reference = Reference::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(CreateReportPage::class, [
        'subjectType' => 'reference',
        'subjectId' => $reference->id,
    ])
        ->set('data.category', 'fake_reference')
        ->set('data.description', 'This listing points to a fabricated title.')
        ->call('submit')
        ->assertRedirect(route('references.show', $reference));

    $this->assertDatabaseHas('reports', [
        'entity_type' => 'reference',
        'entity_id' => $reference->id,
        'category' => 'fake_reference',
    ]);

    expect(SignalEvent::query()->where('event_name', 'report.submitted')->exists())->toBeTrue();
});

it('redirects guests to login before opening report and suggest update pages', function () {
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $this->get(route('contributions.suggest-update', ['subjectType' => 'speaker', 'subjectId' => $speaker->slug]))
        ->assertRedirect(route('login'));

    $this->get(route('reports.create', ['subjectType' => 'speaker', 'subjectId' => $speaker->slug]))
        ->assertRedirect(route('login'));

    $this->get("/report/speaker/{$speaker->slug}")
        ->assertRedirect(route('login'));
});

it('forbids users banned from directory feedback from opening update and report pages', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('feedback.blocked');
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $this->actingAs($user);

    $this->get(route('contributions.suggest-update', ['subjectType' => 'speaker', 'subjectId' => $speaker->slug]))
        ->assertForbidden();

    $this->get(route('reports.create', ['subjectType' => 'speaker', 'subjectId' => $speaker->slug]))
        ->assertForbidden();
});

it('resolves speaker slugs on the update suggestion page without uuid casting errors', function () {
    $user = User::factory()->create();
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(SuggestUpdate::class, [
        'subjectType' => 'speaker',
        'subjectId' => $speaker->slug,
    ])->assertSet('subjectType', 'speaker');
});

it('resolves institution slugs on the report page without uuid casting errors', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(CreateReportPage::class, [
        'subjectType' => 'institution',
        'subjectId' => $institution->slug,
    ])->assertSet('subjectType', 'institution');
});
