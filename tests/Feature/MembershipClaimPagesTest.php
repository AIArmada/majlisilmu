<?php

use App\Enums\MembershipClaimStatus;
use App\Enums\MemberSubjectType;
use App\Livewire\Pages\Contributions\Index as ContributionsIndex;
use App\Livewire\Pages\MembershipClaims\Create as CreateMembershipClaimPage;
use App\Livewire\Pages\MembershipClaims\Index as MembershipClaimsIndex;
use App\Models\Institution;
use App\Models\MembershipClaim;
use App\Models\Speaker;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');
});

it('redirects guests to login for membership claim routes', function () {
    $institution = Institution::factory()->create(['status' => 'verified']);

    $this->get(route('membership-claims.create', [
        'subjectType' => MemberSubjectType::Institution->publicRouteSegment(),
        'subjectId' => $institution->slug,
    ]))->assertRedirect(route('login'));

    $this->get(route('membership-claims.index'))
        ->assertRedirect(route('login'));
});

it('lets authenticated users submit an institution claim with evidence', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create(['status' => 'verified']);

    Livewire::actingAs($user)
        ->test(CreateMembershipClaimPage::class, [
            'subjectType' => MemberSubjectType::Institution->publicRouteSegment(),
            'subjectId' => $institution->slug,
        ])
        ->fillForm([
            'justification' => 'I am part of the institution admin team.',
            'evidence' => [
                UploadedFile::fake()->image('proof.png', 1200, 800),
            ],
        ])
        ->call('submit')
        ->assertRedirect(route('membership-claims.index'));

    $claim = MembershipClaim::query()->where('claimant_id', $user->getKey())->firstOrFail();

    expect($claim->subject_type)->toBe(MemberSubjectType::Institution)
        ->and($claim->status)->toBe(MembershipClaimStatus::Pending)
        ->and($claim->getMedia('evidence'))->toHaveCount(1);
});

it('requires justification and evidence on the public claim form', function () {
    $user = User::factory()->create();
    $speaker = Speaker::factory()->create(['status' => 'verified', 'is_active' => true]);

    Livewire::actingAs($user)
        ->test(CreateMembershipClaimPage::class, [
            'subjectType' => MemberSubjectType::Speaker->publicRouteSegment(),
            'subjectId' => $speaker->slug,
        ])
        ->call('submit')
        ->assertHasErrors([
            'data.justification',
            'data.evidence',
        ]);
});

it('lets claimants cancel pending claims from the history page', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create();
    $claim = MembershipClaim::factory()
        ->forInstitution($institution)
        ->create([
            'claimant_id' => $user->getKey(),
            'status' => MembershipClaimStatus::Pending,
        ]);

    Livewire::actingAs($user)
        ->test(MembershipClaimsIndex::class)
        ->call('cancel', $claim->getKey())
        ->assertHasNoErrors();

    expect($claim->fresh()->status)->toBe(MembershipClaimStatus::Cancelled);
});

it('starts a membership claim from the contributions page search form', function () {
    $user = User::factory()->create();
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(ContributionsIndex::class)
        ->fillForm([
            'subject_type' => MemberSubjectType::Speaker->value,
            'subject_slug' => $speaker->slug,
        ])
        ->call('startMembershipClaim')
        ->assertRedirect(route('membership-claims.create', [
            'subjectType' => MemberSubjectType::Speaker->publicRouteSegment(),
            'subjectId' => $speaker->slug,
        ]));
});

it('does not show membership claim call to action on public institution and speaker pages', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $institutionClaimUrl = route('membership-claims.create', [
        'subjectType' => MemberSubjectType::Institution->publicRouteSegment(),
        'subjectId' => $institution->slug,
    ]);
    $speakerClaimUrl = route('membership-claims.create', [
        'subjectType' => MemberSubjectType::Speaker->publicRouteSegment(),
        'subjectId' => $speaker->slug,
    ]);

    $this->actingAs($user)
        ->get(route('institutions.show', $institution))
        ->assertSuccessful()
        ->assertDontSee($institutionClaimUrl, false)
        ->assertDontSee('Tuntut Keahlian');

    $this->actingAs($user)
        ->get(route('speakers.show', $speaker))
        ->assertSuccessful()
        ->assertDontSee($speakerClaimUrl, false)
        ->assertDontSee('Tuntut Keahlian');
});
