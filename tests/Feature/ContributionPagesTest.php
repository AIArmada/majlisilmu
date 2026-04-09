<?php

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\Signals\Models\SignalEvent;
use App\Enums\ContactCategory;
use App\Enums\ContactType;
use App\Enums\ContributionRequestStatus;
use App\Enums\ContributionRequestType;
use App\Enums\ContributionSubjectType;
use App\Livewire\Pages\Contributions\Index as ContributionsIndex;
use App\Livewire\Pages\Contributions\SubmitInstitution;
use App\Livewire\Pages\Contributions\SubmitSpeaker;
use App\Livewire\Pages\Contributions\SuggestUpdate;
use App\Livewire\Pages\Reports\Create as CreateReportPage;
use App\Models\ContributionRequest;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use App\Models\User;
use App\Support\Authz\MemberRoleScopes;
use App\Support\Authz\ScopedMemberRoleSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        ->assertSee(__('Add a New Institution'))
        ->assertSee(__('Address'))
        ->assertSee(__('Check the existing directory first'))
        ->assertSee(__('Before you submit, please check the existing institutions directory and make sure the institution is not already listed. If it already exists, submit an update instead of creating a duplicate record.'))
        ->assertSee(__('Check Existing Institutions'))
        ->assertDontSee(__('Need to add a speaker instead?'))
        ->assertDontSee(__('Submit Speaker'))
        ->assertDontSee(__('What happens next?'))
        ->assertDontSee(__('Submission Note'))
        ->assertDontSee('lg:grid-cols-2', false);
});

it('renders the institution contribution page with translated copy when the locale changes', function () {
    $user = User::factory()->create();

    app()->setLocale('ms');
    $this->actingAs($user);

    $this->get(route('contributions.submit-institution'))
        ->assertOk()
        ->assertSee('Sumbangan Komuniti')
        ->assertSee('Tambah Institusi Baharu')
        ->assertSee('Profil Institusi')
        ->assertSee('Alamat')
        ->assertSee('Semak direktori sedia ada dahulu')
        ->assertSee('Semak Institusi Sedia Ada')
        ->assertDontSee('Apa yang berlaku seterusnya?')
        ->assertSee('Lihat Sumbangan Saya');
});

it('renders the dedicated speaker contribution page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get(route('contributions.submit-speaker'))
        ->assertOk()
        ->assertSee(__('Add a New Speaker'))
        ->assertDontSee(__('Submission Note'));
});

it('keeps reviewer context fields on update suggestion pages', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
    ]);

    $this->actingAs($user);

    $this->get(route('contributions.suggest-update', ['subjectType' => 'institusi', 'subjectId' => $institution->slug]))
        ->assertOk()
        ->assertSee(__('Context for reviewers'))
        ->assertSee(__('Explain the change'));
});

it('shows the institution cover upload on the suggest update page only for maintainers', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
    ]);

    $this->actingAs($visitor);

    $this->get(route('contributions.suggest-update', [
        'subjectType' => ContributionSubjectType::Institution->publicRouteSegment(),
        'subjectId' => $institution->slug,
    ]))
        ->assertOk()
        ->assertDontSee(__('Cover Image'));

    assignInstitutionOwner($owner, $institution);
    $this->actingAs($owner);

    $this->get(route('contributions.suggest-update', [
        'subjectType' => ContributionSubjectType::Institution->publicRouteSegment(),
        'subjectId' => $institution->slug,
    ]))
        ->assertOk()
        ->assertSee(__('Cover Image'));
});

it('exposes Filament action handlers required by public contribution media uploads', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
    ]);
    assignInstitutionOwner($user, $institution);

    $this->actingAs($user);

    expect(method_exists(Livewire::test(SubmitInstitution::class)->instance(), 'mountAction'))->toBeTrue()
        ->and(method_exists(Livewire::test(SubmitSpeaker::class)->instance(), 'mountAction'))->toBeTrue()
        ->and(method_exists(Livewire::test(SuggestUpdate::class, [
            'subjectType' => ContributionSubjectType::Institution->publicRouteSegment(),
            'subjectId' => $institution->slug,
        ])->instance(), 'mountAction'))->toBeTrue();
});

it('applies direct institution edits for owner maintainers from the suggest update page', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'name' => 'Masjid Lama',
        'nickname' => null,
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
        ->set('data.nickname', 'Masjid Segar')
        ->call('submit')
        ->assertHasNoErrors();

    expect($institution->fresh()->name)->toBe('Masjid Baru')
        ->and($institution->fresh()->nickname)->toBe('Masjid Segar')
        ->and(ContributionRequest::query()->count())->toBe(0);
});

it('applies direct institution edits when an existing phone contact is present on the suggest update page', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'name' => 'Masjid Lama',
        'nickname' => null,
        'status' => 'verified',
    ]);
    $institution->contacts()->delete();
    $institution->contacts()->create([
        'category' => ContactCategory::Phone->value,
        'type' => ContactType::Main->value,
        'value' => '+60112223344',
        'is_public' => true,
    ]);

    assignInstitutionOwner($user, $institution);
    $this->actingAs($user);

    Livewire::test(SuggestUpdate::class, [
        'subjectType' => 'institution',
        'subjectId' => $institution->slug,
    ])
        ->set('data.nickname', 'Masjid Telefon')
        ->call('submit')
        ->assertHasNoErrors();

    expect($institution->fresh()->nickname)->toBe('Masjid Telefon')
        ->and($institution->fresh()->contacts()->where('category', ContactCategory::Phone->value)->value('value'))
        ->not->toBeNull()
        ->not->toBeEmpty()
        ->and(ContributionRequest::query()->count())->toBe(0);
});

it('applies direct institution address edits for owner maintainers from the suggest update page', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
    ]);
    $institution->contacts()->delete();

    assignInstitutionOwner($user, $institution);
    $this->actingAs($user);

    Livewire::test(SuggestUpdate::class, [
        'subjectType' => 'institution',
        'subjectId' => $institution->slug,
    ])
        ->set('data.address.line1', 'No. 21, Jalan Disemak')
        ->call('submit')
        ->assertHasNoErrors();

    expect($institution->fresh()->addressModel?->line1)->toBe('No. 21, Jalan Disemak')
        ->and(ContributionRequest::query()->count())->toBe(0);
});

it('applies direct institution cover edits for owner maintainers from the suggest update page', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
    ]);

    assignInstitutionOwner($user, $institution);
    $this->actingAs($user);

    Livewire::test(SuggestUpdate::class, [
        'subjectType' => ContributionSubjectType::Institution->publicRouteSegment(),
        'subjectId' => $institution->slug,
    ])
        ->fillForm([
            'cover' => UploadedFile::fake()->image('institution-cover.jpg', 1600, 900),
        ])
        ->call('submit')
        ->assertHasNoErrors();

    expect($institution->fresh()->getMedia('cover'))->toHaveCount(1)
        ->and($institution->fresh()->getFirstMedia('cover'))->not->toBeNull()
        ->and(ContributionRequest::query()->count())->toBe(0);
});

it('re-moderates approved events when maintainers apply sensitive direct edits from the suggest update page', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
    ]);
    $event = Event::factory()->for($institution)->create([
        'title' => 'Majlis Sensitif',
        'status' => 'approved',
        'starts_at' => now()->addDays(4),
        'ends_at' => now()->addDays(4)->addHour(),
    ]);

    assignInstitutionOwner($user, $institution);
    $this->actingAs($user);

    Livewire::test(SuggestUpdate::class, [
        'subjectType' => ContributionSubjectType::Event->publicRouteSegment(),
        'subjectId' => $event->slug,
    ])
        ->set('data.starts_at', now()->addDays(8)->toDateTimeString())
        ->call('submit')
        ->assertHasNoErrors();

    expect((string) $event->fresh()->status)->toBe('pending')
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
        ->and(trim(strip_tags((string) ($request?->proposed_data['description'] ?? ''))))->toBe('Updated community description')
        ->and(data_get($request?->proposed_data, 'address.line1'))->toBe('No. 8, Jalan Baru');
});

it('shows the latest pending request notice on the suggest update page', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
    ]);
    ContributionRequest::factory()->create([
        'proposer_id' => $user->id,
        'entity_type' => $institution->getMorphClass(),
        'entity_id' => $institution->id,
        'status' => ContributionRequestStatus::Pending,
    ]);

    $this->actingAs($user);

    Livewire::test(SuggestUpdate::class, [
        'subjectType' => 'institution',
        'subjectId' => $institution->slug,
    ])
        ->assertSee(__('Pending Request'))
        ->assertSee('You already have a pending update request for this record from');
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

it('lets proposers cancel their own pending requests from the contributions inbox', function () {
    $proposer = User::factory()->create();
    $request = ContributionRequest::factory()->create([
        'proposer_id' => $proposer->id,
        'status' => ContributionRequestStatus::Pending,
    ]);

    $this->actingAs($proposer);

    Livewire::test(ContributionsIndex::class)
        ->call('cancel', $request->id);

    expect($request->fresh()->status)->toBe(ContributionRequestStatus::Cancelled)
        ->and($request->fresh()->cancelled_at)->not->toBeNull();
});

it('stores reference reports from the public report page', function () {
    $user = User::factory()->create();
    $reference = Reference::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $referenceRouteSegment = ContributionSubjectType::Reference->publicRouteSegment();

    $this->actingAs($user);

    Livewire::test(CreateReportPage::class, [
        'subjectType' => $referenceRouteSegment,
        'subjectId' => $reference->slug,
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
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $reference = Reference::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'published_at' => now(),
        'starts_at' => now()->addDay(),
    ]);

    $eventRouteSegment = ContributionSubjectType::Event->publicRouteSegment();
    $institutionRouteSegment = ContributionSubjectType::Institution->publicRouteSegment();
    $speakerRouteSegment = ContributionSubjectType::Speaker->publicRouteSegment();
    $referenceRouteSegment = ContributionSubjectType::Reference->publicRouteSegment();

    $this->get(route('contributions.suggest-update', ['subjectType' => $institutionRouteSegment, 'subjectId' => $institution->slug]))
        ->assertRedirect(route('login'));

    $this->get(route('reports.create', ['subjectType' => $institutionRouteSegment, 'subjectId' => $institution->slug]))
        ->assertRedirect(route('login'));

    $this->get(route('contributions.suggest-update', ['subjectType' => $speakerRouteSegment, 'subjectId' => $speaker->slug]))
        ->assertRedirect(route('login'));

    $this->get(route('reports.create', ['subjectType' => $speakerRouteSegment, 'subjectId' => $speaker->slug]))
        ->assertRedirect(route('login'));

    $this->get("/sumbangan/speaker/{$speaker->slug}/kemas-kini")
        ->assertRedirect("/sumbangan/{$speakerRouteSegment}/{$speaker->slug}/kemas-kini");

    $this->get("/lapor/speaker/{$speaker->slug}")
        ->assertRedirect("/lapor/{$speakerRouteSegment}/{$speaker->slug}");

    $this->get("/sumbangan/institution/{$institution->slug}/kemas-kini")
        ->assertRedirect("/sumbangan/{$institutionRouteSegment}/{$institution->slug}/kemas-kini");

    $this->get("/lapor/institution/{$institution->slug}")
        ->assertRedirect("/lapor/{$institutionRouteSegment}/{$institution->slug}");

    $this->get("/sumbangan/event/{$event->slug}/kemas-kini")
        ->assertRedirect("/sumbangan/{$eventRouteSegment}/{$event->slug}/kemas-kini");

    $this->get("/lapor/event/{$event->slug}")
        ->assertRedirect("/lapor/{$eventRouteSegment}/{$event->slug}");

    $this->get("/sumbangan/reference/{$reference->slug}/kemas-kini")
        ->assertRedirect("/sumbangan/{$referenceRouteSegment}/{$reference->slug}/kemas-kini");

    $this->get("/lapor/reference/{$reference->slug}")
        ->assertRedirect("/lapor/{$referenceRouteSegment}/{$reference->slug}");
});

it('forbids users banned from directory feedback from opening update and report pages', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('feedback.blocked');
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $this->actingAs($user);

    $this->get(route('contributions.suggest-update', ['subjectType' => ContributionSubjectType::Speaker->publicRouteSegment(), 'subjectId' => $speaker->slug]))
        ->assertForbidden();

    $this->get(route('reports.create', ['subjectType' => ContributionSubjectType::Speaker->publicRouteSegment(), 'subjectId' => $speaker->slug]))
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
        'subjectType' => ContributionSubjectType::Speaker->publicRouteSegment(),
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
        'subjectType' => ContributionSubjectType::Institution->publicRouteSegment(),
        'subjectId' => $institution->slug,
    ])->assertSet('subjectType', 'institution');
});

it('shows the reported institution clearly on the public report page', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'name' => 'Kompleks Islam Senawang',
        'status' => 'verified',
        'is_active' => true,
    ]);
    $selectedInstitutionLabel = __('Selected :subject', ['subject' => strtolower(__('Institution'))]);
    $viewInstitutionLabel = __('View this :subject', ['subject' => strtolower(__('Institution'))]);

    $this->actingAs($user);

    $this->get(route('reports.create', [
        'subjectType' => ContributionSubjectType::Institution->publicRouteSegment(),
        'subjectId' => $institution->slug,
    ]))
        ->assertOk()
        ->assertSeeText($selectedInstitutionLabel)
        ->assertSeeText($institution->name)
        ->assertSeeText($viewInstitutionLabel);
});

it('shows the reported speaker clearly on the public report page', function () {
    $user = User::factory()->create();
    $speaker = Speaker::factory()->create([
        'name' => 'Amina binti Rashid',
        'status' => 'verified',
        'is_active' => true,
    ]);
    $selectedSpeakerLabel = __('Selected :subject', ['subject' => strtolower(__('Speaker'))]);
    $viewSpeakerLabel = __('View this :subject', ['subject' => strtolower(__('Speaker'))]);

    $this->actingAs($user);

    $this->get(route('reports.create', [
        'subjectType' => ContributionSubjectType::Speaker->publicRouteSegment(),
        'subjectId' => $speaker->slug,
    ]))
        ->assertOk()
        ->assertSeeText($selectedSpeakerLabel)
        ->assertSeeText($speaker->formatted_name)
        ->assertSeeText($viewSpeakerLabel);
});

it('shows the reported event clearly on the public report page', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create([
        'title' => 'Kelas Daurah Tafsir Ibnu Kathir',
        'status' => 'approved',
        'is_active' => true,
    ]);
    $selectedEventLabel = __('Selected :subject', ['subject' => strtolower(__('Event'))]);
    $viewEventLabel = __('View this :subject', ['subject' => strtolower(__('Event'))]);

    $this->actingAs($user);

    $this->get(route('reports.create', [
        'subjectType' => ContributionSubjectType::Event->publicRouteSegment(),
        'subjectId' => $event->slug,
    ]))
        ->assertOk()
        ->assertSeeText($selectedEventLabel)
        ->assertSeeText($event->title)
        ->assertSeeText($viewEventLabel);
});

it('redirects uuid-based reference contribution and report pages to the canonical slug url', function () {
    $user = User::factory()->create();
    $reference = Reference::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $this->actingAs($user);

    $this->get("/sumbangan/rujukan/{$reference->id}/kemas-kini")
        ->assertRedirect(route('contributions.suggest-update', [
            'subjectType' => ContributionSubjectType::Reference->publicRouteSegment(),
            'subjectId' => $reference->slug,
        ]));

    $this->get("/lapor/rujukan/{$reference->id}")
        ->assertRedirect(route('reports.create', [
            'subjectType' => ContributionSubjectType::Reference->publicRouteSegment(),
            'subjectId' => $reference->slug,
        ]));
});
