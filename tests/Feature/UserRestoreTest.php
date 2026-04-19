<?php

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\PendingConversion;
use App\Models\Event;
use App\Models\Institution;
use App\Models\NotificationSetting;
use App\Models\Reference;
use App\Models\SocialAccount;
use App\Models\Speaker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class);

it('restores a deleted user together with key relationships and child records', function () {
    $user = User::factory()->create([
        'name' => 'Restore Me',
        'email' => 'restore-me@example.test',
        'phone' => '+60120000000',
    ]);

    $institution = Institution::factory()->create();
    $speaker = Speaker::factory()->create();
    $reference = Reference::factory()->create();
    $followedInstitution = Institution::factory()->create();
    $followedSpeaker = Speaker::factory()->create();
    $followedReference = Reference::factory()->create();
    $ownedEvent = Event::factory()->create([
        'user_id' => $user->id,
    ]);
    $sharedEvent = Event::factory()->create();

    SocialAccount::factory()->create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-restore-user',
    ]);

    NotificationSetting::factory()->create([
        'user_id' => $user->id,
        'locale' => 'ms',
        'timezone' => 'UTC',
    ]);

    $affiliate = new Affiliate([
        'code' => 'restore-me-affiliate',
        'name' => 'Restore Me Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage->value,
        'commission_rate' => 1500,
        'currency' => 'MYR',
        'metadata' => [
            'tracking_case' => 'restore-test',
        ],
        'activated_at' => now(),
    ]);

    $affiliate->assignOwner($user);
    $affiliate->save();

    $affiliateLink = AffiliateLink::query()->create([
        'affiliate_id' => $affiliate->id,
        'destination_url' => 'https://majlisilmu.test/events/restore-me',
        'tracking_url' => 'https://majlisilmu.test/r/restore-me',
        'short_url' => 'https://majlisilmu.test/s/restore-me',
        'custom_slug' => 'restore-me',
        'campaign' => 'restore-user',
        'subject_type' => 'event',
        'subject_identifier' => 'restore-me-event',
        'subject_instance' => 'web',
        'subject_title_snapshot' => 'Restore Me Event',
        'subject_metadata' => [
            'source' => 'test',
        ],
        'is_active' => true,
    ]);

    $affiliateAttribution = AffiliateAttribution::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'subject_type' => 'event',
        'subject_identifier' => 'restore-me-event',
        'subject_instance' => 'web',
        'subject_title_snapshot' => 'Restore Me Event',
        'cart_identifier' => 'cart-restore-me',
        'cart_instance' => 'web',
        'cookie_value' => 'cookie-restore-me',
        'source' => 'web',
        'medium' => 'social',
        'campaign' => 'restore-user',
        'landing_url' => 'https://majlisilmu.test/events/restore-me',
        'referrer_url' => 'https://example.com',
        'user_agent' => 'Pest',
        'ip_address' => '127.0.0.1',
        'metadata' => [
            'share_provider' => 'web',
        ],
        'first_seen_at' => now()->subDay(),
        'last_seen_at' => now(),
        'last_cookie_seen_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $affiliateTouchpoint = AffiliateTouchpoint::query()->create([
        'affiliate_attribution_id' => $affiliateAttribution->id,
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'subject_type' => 'event',
        'subject_identifier' => 'restore-me-event',
        'subject_instance' => 'web',
        'subject_title_snapshot' => 'Restore Me Event',
        'source' => 'web',
        'medium' => 'social',
        'campaign' => 'restore-user',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'metadata' => [
            'share_provider' => 'web',
        ],
        'touched_at' => now(),
    ]);

    $affiliateConversion = AffiliateConversion::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_attribution_id' => $affiliateAttribution->id,
        'affiliate_code' => $affiliate->code,
        'subject_type' => 'event',
        'subject_identifier' => 'restore-me-event',
        'subject_instance' => 'web',
        'subject_title_snapshot' => 'Restore Me Event',
        'cart_identifier' => 'cart-restore-me',
        'cart_instance' => 'web',
        'voucher_code' => 'RESTORE-ME',
        'external_reference' => 'order-restore-me',
        'order_reference' => 'order-restore-me',
        'conversion_type' => 'signup',
        'subtotal_minor' => 10000,
        'value_minor' => 10000,
        'total_minor' => 10000,
        'commission_minor' => 1500,
        'commission_currency' => 'MYR',
        'status' => PendingConversion::class,
        'channel' => 'web',
        'metadata' => [
            'share_provider' => 'web',
            'affiliate_link_id' => $affiliateLink->id,
        ],
        'occurred_at' => now(),
    ]);

    $user->institutions()->attach($institution->id);
    $user->speakers()->attach($speaker->id);
    $user->references()->attach($reference->id);

    $user->followingInstitutions()->attach($followedInstitution->id);
    $user->followingSpeakers()->attach($followedSpeaker->id);
    $user->followingReferences()->attach($followedReference->id);
    $user->savedEvents()->attach($sharedEvent->id);
    $user->goingEvents()->attach($sharedEvent->id);
    $user->memberEvents()->attach($sharedEvent->id, ['joined_at' => now()]);

    $user->delete();

    assertDatabaseHas('deleted_models', [
        'key' => $user->id,
        'model' => $user->getMorphClass(),
    ]);

    assertDatabaseMissing($affiliate->getTable(), [
        'id' => $affiliate->id,
    ]);

    assertDatabaseMissing($affiliateLink->getTable(), [
        'id' => $affiliateLink->id,
    ]);

    assertDatabaseMissing($affiliateAttribution->getTable(), [
        'id' => $affiliateAttribution->id,
    ]);

    assertDatabaseMissing($affiliateTouchpoint->getTable(), [
        'id' => $affiliateTouchpoint->id,
    ]);

    assertDatabaseMissing($affiliateConversion->getTable(), [
        'id' => $affiliateConversion->id,
    ]);

    assertDatabaseMissing($affiliate->getTable(), [
        'id' => $affiliate->id,
    ]);

    $restoredUser = User::restoreDeletedUser($user->id);

    expect($restoredUser->exists)->toBeTrue();

    assertDatabaseMissing('deleted_models', [
        'key' => $user->id,
        'model' => $user->getMorphClass(),
    ]);

    assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Restore Me',
        'email' => 'restore-me@example.test',
    ]);

    assertDatabaseHas($affiliate->getTable(), [
        'id' => $affiliate->id,
        'owner_type' => $user->getMorphClass(),
        'owner_id' => $user->id,
    ]);

    assertDatabaseHas('institution_user', [
        'institution_id' => $institution->id,
        'user_id' => $user->id,
    ]);

    assertDatabaseHas('speaker_user', [
        'speaker_id' => $speaker->id,
        'user_id' => $user->id,
    ]);

    assertDatabaseHas('reference_user', [
        'reference_id' => $reference->id,
        'user_id' => $user->id,
    ]);

    assertDatabaseHas('followings', [
        'user_id' => $user->id,
        'followable_id' => $followedInstitution->id,
        'followable_type' => $followedInstitution->getMorphClass(),
    ]);

    assertDatabaseHas('followings', [
        'user_id' => $user->id,
        'followable_id' => $followedSpeaker->id,
        'followable_type' => $followedSpeaker->getMorphClass(),
    ]);

    assertDatabaseHas('followings', [
        'user_id' => $user->id,
        'followable_id' => $followedReference->id,
        'followable_type' => $followedReference->getMorphClass(),
    ]);

    assertDatabaseHas('event_saves', [
        'event_id' => $sharedEvent->id,
        'user_id' => $user->id,
    ]);

    assertDatabaseHas('event_attendees', [
        'event_id' => $sharedEvent->id,
        'user_id' => $user->id,
    ]);

    assertDatabaseHas('event_user', [
        'event_id' => $sharedEvent->id,
        'user_id' => $user->id,
    ]);

    assertDatabaseHas('events', [
        'id' => $ownedEvent->id,
        'user_id' => $user->id,
    ]);

    assertDatabaseHas('socialite', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-restore-user',
    ]);

    assertDatabaseHas('notification_settings', [
        'user_id' => $user->id,
        'locale' => 'ms',
    ]);

    assertDatabaseHas($affiliate->getTable(), [
        'id' => $affiliate->id,
        'code' => 'restore-me-affiliate',
        'name' => 'Restore Me Affiliate',
    ]);

    assertDatabaseHas($affiliateLink->getTable(), [
        'id' => $affiliateLink->id,
        'affiliate_id' => $affiliate->id,
    ]);

    assertDatabaseHas($affiliateAttribution->getTable(), [
        'id' => $affiliateAttribution->id,
        'affiliate_id' => $affiliate->id,
    ]);

    assertDatabaseHas($affiliateTouchpoint->getTable(), [
        'id' => $affiliateTouchpoint->id,
        'affiliate_id' => $affiliate->id,
        'affiliate_attribution_id' => $affiliateAttribution->id,
    ]);

    assertDatabaseHas($affiliateConversion->getTable(), [
        'id' => $affiliateConversion->id,
        'affiliate_id' => $affiliate->id,
        'affiliate_attribution_id' => $affiliateAttribution->id,
    ]);
});
