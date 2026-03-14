<?php

use App\Actions\Membership\AcceptSubjectMemberInvitation;
use App\Actions\Membership\InviteSubjectMember;
use App\Actions\Membership\RevokeSubjectMemberInvitation;
use App\Enums\MemberSubjectType;
use App\Models\Institution;
use App\Models\MemberInvitation;
use App\Models\User;
use App\Support\Authz\MemberRoleCatalog;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('creates a member invitation with the requested subject and role', function () {
    $institution = Institution::factory()->create();
    $inviter = User::factory()->create();

    $invitation = app(InviteSubjectMember::class)->handle(
        $institution,
        'Invitee@example.com',
        'owner',
        $inviter,
        now()->addWeek(),
    );

    expect($invitation)->toBeInstanceOf(MemberInvitation::class)
        ->and($invitation->subject_type)->toBe(MemberSubjectType::Institution)
        ->and($invitation->subject_id)->toBe($institution->getKey())
        ->and($invitation->email)->toBe('invitee@example.com')
        ->and($invitation->role_slug)->toBe('owner')
        ->and($invitation->invited_by)->toBe($inviter->getKey())
        ->and($invitation->token)->not->toBe('');
});

it('accepts a member invitation and routes membership through the shared scoped role actions', function () {
    $institution = Institution::factory()->create();
    $inviter = User::factory()->create();
    $invitee = User::factory()->create([
        'email' => 'invitee@example.com',
        'phone' => '+60112223344',
        'phone_verified_at' => now(),
    ]);

    $invitation = app(InviteSubjectMember::class)->handle(
        $institution,
        $invitee->email,
        'admin',
        $inviter,
        now()->addWeek(),
    );

    app(AcceptSubjectMemberInvitation::class)->handle($invitation, $invitee);

    $invitation->refresh();

    expect($institution->members()->whereKey($invitee->getKey())->exists())->toBeTrue()
        ->and(app(MemberRoleCatalog::class)->roleNamesFor($invitee, MemberSubjectType::Institution))->toBe(['admin'])
        ->and($invitation->accepted_at)->not->toBeNull()
        ->and($invitation->accepted_by)->toBe($invitee->getKey());
});

it('rejects expired invitations', function () {
    $institution = Institution::factory()->create();
    $inviter = User::factory()->create();
    $invitee = User::factory()->create([
        'email' => 'invitee@example.com',
    ]);

    $invitation = app(InviteSubjectMember::class)->handle(
        $institution,
        $invitee->email,
        'viewer',
        $inviter,
        now()->subMinute(),
    );

    expect(fn () => app(AcceptSubjectMemberInvitation::class)->handle($invitation, $invitee))
        ->toThrow(ValidationException::class);
});

it('rejects revoked invitations', function () {
    $institution = Institution::factory()->create();
    $inviter = User::factory()->create();
    $invitee = User::factory()->create([
        'email' => 'invitee@example.com',
    ]);

    $invitation = app(InviteSubjectMember::class)->handle(
        $institution,
        $invitee->email,
        'viewer',
        $inviter,
        now()->addWeek(),
    );

    app(RevokeSubjectMemberInvitation::class)->handle($invitation, $inviter);

    expect(fn () => app(AcceptSubjectMemberInvitation::class)->handle($invitation->fresh(), $invitee))
        ->toThrow(ValidationException::class);
});

it('rejects reused invitations after they have already been accepted', function () {
    $institution = Institution::factory()->create();
    $inviter = User::factory()->create();
    $invitee = User::factory()->create([
        'email' => 'invitee@example.com',
        'phone' => '+60112223344',
        'phone_verified_at' => now(),
    ]);

    $invitation = app(InviteSubjectMember::class)->handle(
        $institution,
        $invitee->email,
        'viewer',
        $inviter,
        now()->addWeek(),
    );

    app(AcceptSubjectMemberInvitation::class)->handle($invitation, $invitee);

    expect(fn () => app(AcceptSubjectMemberInvitation::class)->handle($invitation->fresh(), $invitee))
        ->toThrow(ValidationException::class);
});
