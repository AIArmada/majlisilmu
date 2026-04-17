<?php

namespace App\Support\Api\Frontend;

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\Gender;
use App\Enums\Honorific;
use App\Enums\InstitutionType;
use App\Enums\MemberSubjectType;
use App\Enums\PostNominal;
use App\Enums\PreNominal;
use App\Enums\RegistrationMode;
use App\Enums\TagType;
use App\Models\User;
use App\Support\ApiDocumentation\ApiDocumentationUrlResolver;
use App\Support\Location\GooglePlacesConfiguration;
use App\Support\Location\PreferredCountryResolver;
use App\Support\Location\PublicCountryRegistry;
use App\Support\Mcp\McpTokenManager;

class FrontendFormContractService
{
    public function __construct(
        private readonly FrontendCatalogService $catalogService,
        private readonly ApiDocumentationUrlResolver $urlResolver,
        private readonly McpTokenManager $mcpTokenManager,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function manifest(?User $user): array
    {
        return [
            'version' => '2026-04-16',
            'docs' => [
                'ui' => $this->urlResolver->docsUrl(),
                'openapi' => $this->urlResolver->docsJsonUrl(),
                'api_base' => $this->urlResolver->apiBaseUrl(),
            ],
            'routing_surfaces' => [
                'public' => [
                    'base_path' => '/api/v1',
                    'record_scope' => 'Only active and verified public records.',
                    'manifest_endpoint' => route('api.client.manifest'),
                    'write_contract_family' => 'GET /forms/*',
                ],
                'admin' => [
                    'base_path' => '/api/v1/admin',
                    'record_scope' => 'All accessible records, including inactive, pending, and rejected records.',
                    'manifest_endpoint' => route('api.admin.manifest'),
                    'write_contract_family' => 'GET /admin/{resourceKey}/schema',
                ],
            ],
            'ai_quickstart' => [
                'read_order' => [
                    [
                        'step' => 1,
                        'action' => 'Fetch the machine-readable OpenAPI document.',
                        'endpoint' => $this->urlResolver->docsJsonUrl(),
                    ],
                    [
                        'step' => 2,
                        'action' => 'Discover public client workflows and endpoint templates.',
                        'endpoint' => route('api.client.manifest'),
                    ],
                    [
                        'step' => 3,
                        'action' => 'Before any public write, fetch the exact public form contract.',
                        'endpoint_family' => 'GET /forms/*',
                    ],
                    [
                        'step' => 4,
                        'action' => 'Before any admin write, fetch the admin resource manifest then the exact resource schema.',
                        'endpoint' => route('api.admin.manifest'),
                    ],
                ],
                'decision_points' => [
                    [
                        'need' => 'Public search, public detail pages, and client-parity reads.',
                        'use_surface' => 'public',
                    ],
                    [
                        'need' => 'Admin search, inactive or pending records, and schema-driven writes.',
                        'use_surface' => 'admin',
                    ],
                ],
            ],
            'rules' => [
                'Treat error.code as the machine-readable failure type.',
                'Use meta.request_id for request tracing and support handoff.',
                'Use UTC timestamps and UTC date-filter boundaries.',
                'Use the admin record route_key returned by admin collection or detail payloads for record-specific schema and mutation paths.',
                'Fetch the exact form or schema contract before sending write payloads.',
            ],
            'catalogs' => [
                'countries' => route('api.client.catalogs.countries'),
                'states' => route('api.client.catalogs.states'),
                'districts' => route('api.client.catalogs.districts'),
                'subdistricts' => route('api.client.catalogs.subdistricts'),
                'languages' => route('api.client.catalogs.languages'),
                'submit_speakers' => route('api.client.catalogs.submit-speakers'),
                'submit_institutions' => route('api.client.catalogs.submit-institutions'),
                'venues' => route('api.client.catalogs.venues'),
                'references' => route('api.client.catalogs.references'),
                'tags_template' => route('api.client.catalogs.tags', ['type' => 'type'], false),
                'prayer_institutions' => $user instanceof User ? route('api.client.catalogs.prayer-institutions') : null,
                'institution_roles' => $user instanceof User ? route('api.client.catalogs.institution-roles') : null,
            ],
            'flows' => [
                'search' => [
                    'method' => 'GET',
                    'endpoint' => route('api.client.search.index'),
                    'auth_required' => false,
                ],
                'institutions_index' => [
                    'method' => 'GET',
                    'endpoint' => route('api.client.institutions.index'),
                    'auth_required' => false,
                ],
                'institutions_show' => [
                    'method' => 'GET',
                    'endpoint_template' => route('api.client.institutions.show', ['institutionKey' => 'subject'], false),
                    'auth_required' => false,
                ],
                'speakers_index' => [
                    'method' => 'GET',
                    'endpoint' => route('api.client.speakers.index'),
                    'auth_required' => false,
                ],
                'speakers_show' => [
                    'method' => 'GET',
                    'endpoint_template' => route('api.client.speakers.show', ['speakerKey' => 'subject'], false),
                    'auth_required' => false,
                ],
                'inspirations_random' => [
                    'method' => 'GET',
                    'endpoint' => route('api.client.inspirations.random'),
                    'auth_required' => false,
                ],
                'venues_show' => [
                    'method' => 'GET',
                    'endpoint_template' => route('api.client.venues.show', ['venueKey' => 'subject'], false),
                    'auth_required' => false,
                ],
                'references_show' => [
                    'method' => 'GET',
                    'endpoint_template' => route('api.client.references.show', ['referenceKey' => 'subject'], false),
                    'auth_required' => false,
                ],
                'series_show' => [
                    'method' => 'GET',
                    'endpoint_template' => route('api.client.series.show', ['series' => 'subject'], false),
                    'auth_required' => false,
                ],
                'submit_event' => [
                    'method' => 'POST',
                    'endpoint' => route('api.client.submit-event.store'),
                    'auth_required' => false,
                    'schema_endpoint' => route('api.client.forms.submit-event'),
                ],
                'submit_institution' => [
                    'method' => 'POST',
                    'endpoint' => route('api.client.contributions.institutions.store'),
                    'auth_required' => true,
                    'schema_endpoint' => route('api.client.forms.contributions.institutions'),
                ],
                'submit_speaker' => [
                    'method' => 'POST',
                    'endpoint' => route('api.client.contributions.speakers.store'),
                    'auth_required' => true,
                    'schema_endpoint' => route('api.client.forms.contributions.speakers'),
                ],
                'contribution_update' => [
                    'method' => 'POST',
                    'endpoint_template' => route('api.client.contributions.suggest.store', [
                        'subjectType' => 'subjectType',
                        'subject' => 'subject',
                    ], false),
                    'schema_endpoint_template' => route('api.client.forms.contributions.suggest', [
                        'subjectType' => 'subjectType',
                        'subject' => 'subject',
                    ], false),
                    'auth_required' => true,
                ],
                'contributions_index' => [
                    'method' => 'GET',
                    'endpoint' => route('api.client.contributions.index'),
                    'auth_required' => true,
                ],
                'membership_claim' => [
                    'method' => 'POST',
                    'endpoint_template' => route('api.client.membership-claims.store', [
                        'subjectType' => 'subjectType',
                        'subject' => 'subject',
                    ], false),
                    'schema_endpoint_template' => route('api.client.forms.membership-claims', [
                        'subjectType' => 'subjectType',
                    ], false),
                    'auth_required' => true,
                ],
                'membership_claims_index' => [
                    'method' => 'GET',
                    'endpoint' => route('api.client.membership-claims.index'),
                    'auth_required' => true,
                ],
                'report' => [
                    'method' => 'POST',
                    'endpoint' => route('api.reports.store'),
                    'auth_required' => true,
                ],
                'account_settings' => [
                    'method' => 'PUT',
                    'endpoint' => route('api.client.account-settings.update'),
                    'auth_required' => true,
                ],
                'mcp_tokens' => [
                    'index_endpoint' => route('api.client.account-settings.mcp-tokens.index'),
                    'store_endpoint' => route('api.client.account-settings.mcp-tokens.store'),
                    'destroy_endpoint_template' => route('api.client.account-settings.mcp-tokens.destroy', ['tokenId' => 'token'], false),
                    'auth_required' => true,
                ],
                'advanced_event' => [
                    'method' => 'POST',
                    'endpoint' => route('api.client.advanced-events.store'),
                    'auth_required' => true,
                    'schema_endpoint' => route('api.client.forms.advanced-events'),
                ],
                'institution_workspace' => [
                    'method' => 'GET',
                    'endpoint' => route('api.client.institution-workspace.show'),
                    'auth_required' => true,
                ],
                'follow' => [
                    'state_method' => 'GET',
                    'state_endpoint_template' => route('api.client.follows.show', ['type' => 'type', 'subject' => 'subject'], false),
                    'follow_method' => 'POST',
                    'follow_endpoint_template' => route('api.client.follows.store', ['type' => 'type', 'subject' => 'subject'], false),
                    'unfollow_method' => 'DELETE',
                    'unfollow_endpoint_template' => route('api.client.follows.destroy', ['type' => 'type', 'subject' => 'subject'], false),
                    'auth_required' => true,
                ],
            ],
            'auth_context' => [
                'authenticated' => $user instanceof User,
                'requires_bearer_token_for_mutations' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function submitEvent(?User $user): array
    {
        $publicCountryRegistry = app(PublicCountryRegistry::class);
        $submissionCountryIds = collect($publicCountryRegistry->all())
            ->filter(static fn (array $country): bool => $country['enabled'])
            ->keys()
            ->map(fn (string $countryKey): ?int => $publicCountryRegistry->countryIdForKey($countryKey))
            ->filter(static fn (?int $countryId): bool => is_int($countryId))
            ->values()
            ->all();
        $submissionCountryId = $publicCountryRegistry->normalizeCountryId(
            app(PreferredCountryResolver::class)->resolveId(),
        ) ?? $publicCountryRegistry->countryIdForKey($publicCountryRegistry->defaultKey())
            ?? $publicCountryRegistry->countryIdFromIso2('MY')
            ?? 132;

        return [
            'flow' => 'submit_event',
            'method' => 'POST',
            'endpoint' => route('api.client.submit-event.store'),
            'auth_required' => false,
            'captcha_required_when_turnstile_enabled' => true,
            'defaults' => [
                'parent_event_id' => null,
                'scoped_institution_id' => null,
                'submitter_name' => $user?->name,
                'submitter_email' => $user?->email,
                'children_allowed' => true,
                'gender' => EventGenderRestriction::All->value,
                'age_group' => [EventAgeGroup::AllAges->value],
                'languages' => [101],
                'event_format' => EventFormat::Physical->value,
                'visibility' => EventVisibility::Public->value,
                'location_same_as_institution' => true,
                'location_type' => 'institution',
                'is_muslim_only' => false,
                'other_key_people' => [],
                'captcha_token' => null,
                'submission_country_id' => $submissionCountryId,
            ],
            'fields' => [
                $this->field('title', 'string', required: true, maxLength: 255),
                $this->field('description', 'rich_text', required: false),
                $this->field('parent_event_id', 'uuid', required: false),
                $this->field('scoped_institution_id', 'uuid', required: false),
                $this->field('event_type', 'array<string>', required: true, allowedValues: $this->enumValues(EventType::class)),
                $this->field('event_date', 'date', required: true),
                $this->field('prayer_time', 'string', required: true, allowedValues: $this->enumValues(EventPrayerTime::class)),
                $this->field('custom_time', 'time', required: false),
                $this->field('end_time', 'time', required: false),
                $this->field('event_format', 'string', required: true, default: EventFormat::Physical->value, allowedValues: $this->enumValues(EventFormat::class)),
                $this->field('visibility', 'string', required: true, default: EventVisibility::Public->value, allowedValues: $this->enumValues(EventVisibility::class)),
                $this->field('event_url', 'url', required: false),
                $this->field('live_url', 'url', required: false),
                $this->field('gender', 'string', required: true, default: EventGenderRestriction::All->value, allowedValues: $this->enumValues(EventGenderRestriction::class)),
                $this->field('age_group', 'array<string>', required: true, default: [EventAgeGroup::AllAges->value], allowedValues: $this->enumValues(EventAgeGroup::class)),
                $this->field('children_allowed', 'boolean', required: false, default: true),
                $this->field('is_muslim_only', 'boolean', required: false, default: false),
                $this->field('languages', 'array<int>', required: true, default: [101], catalog: route('api.client.catalogs.languages')),
                $this->field('domain_tags', 'array<string>', required: false, catalog: route('api.client.catalogs.tags', ['type' => TagType::Domain->value])),
                $this->field('discipline_tags', 'array<string>', required: false, catalog: route('api.client.catalogs.tags', ['type' => TagType::Discipline->value])),
                $this->field('source_tags', 'array<string>', required: false, catalog: route('api.client.catalogs.tags', ['type' => TagType::Source->value])),
                $this->field('issue_tags', 'array<string>', required: false, catalog: route('api.client.catalogs.tags', ['type' => TagType::Issue->value])),
                $this->field('references', 'array<string>', required: false, catalog: route('api.client.catalogs.references')),
                $this->field('organizer_type', 'string', required: true, default: 'institution', allowedValues: ['institution', 'speaker']),
                $this->field('organizer_institution_id', 'uuid', required: false, catalog: route('api.client.catalogs.submit-institutions')),
                $this->field('organizer_speaker_id', 'uuid', required: false, catalog: route('api.client.catalogs.submit-speakers')),
                $this->field('location_same_as_institution', 'boolean', required: false, default: true),
                $this->field('location_type', 'string', required: false, default: 'institution', allowedValues: ['institution', 'venue']),
                $this->field('location_institution_id', 'uuid', required: false, catalog: route('api.client.catalogs.submit-institutions')),
                $this->field('location_venue_id', 'uuid', required: false, catalog: route('api.client.catalogs.venues')),
                $this->field('space_id', 'uuid', required: false, catalog: route('api.client.catalogs.spaces')),
                $this->field('speakers', 'array<string>', required: false, catalog: route('api.client.catalogs.submit-speakers')),
                $this->field('other_key_people', 'array<object>', required: false),
                $this->field('submission_country_id', 'integer', required: true, default: $submissionCountryId, allowedValues: $submissionCountryIds),
                $this->field('submitter_name', 'string', required: ! $user instanceof User, maxLength: 255),
                $this->field('submitter_email', 'email', required: false),
                $this->field('submitter_phone', 'string', required: false),
                $this->field('notes', 'string', required: false, maxLength: 1000),
                $this->field('captcha_token', 'string', required: false),
                $this->field('poster', 'file', required: false),
                $this->field('gallery', 'array<file>', required: false),
            ],
            'conditional_rules' => [
                ['field' => 'organizer_institution_id', 'required_when' => ['organizer_type' => ['institution']]],
                ['field' => 'organizer_speaker_id', 'required_when' => ['organizer_type' => ['speaker']]],
                ['field' => 'location_institution_id', 'required_when' => ['location_type' => ['institution']]],
                ['field' => 'location_venue_id', 'required_when' => ['location_type' => ['venue']]],
                ['field' => 'submitter_email', 'required_when_missing' => ['submitter_phone']],
                ['field' => 'submitter_phone', 'required_when_missing' => ['submitter_email']],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function submitInstitution(): array
    {
        $preferredCountryId = app(PreferredCountryResolver::class)->resolveId() ?? PreferredCountryResolver::MALAYSIA_ID;

        return [
            'flow' => 'submit_institution',
            'method' => 'POST',
            'endpoint' => route('api.client.contributions.institutions.store'),
            'auth_required' => true,
            'defaults' => [
                'type' => InstitutionType::Masjid->value,
                'address' => [
                    'country_id' => $preferredCountryId,
                    'state_id' => null,
                    'district_id' => null,
                    'subdistrict_id' => null,
                    'line1' => null,
                    'line2' => null,
                    'postcode' => null,
                    'lat' => null,
                    'lng' => null,
                    'google_maps_url' => null,
                    'google_maps_normalization_enabled' => true,
                    'google_maps_remote_lookup_enabled' => GooglePlacesConfiguration::isEnabled(),
                ],
            ],
            'fields' => [
                $this->field('name', 'string', required: true, maxLength: 255),
                $this->field('nickname', 'string', required: false, maxLength: 255),
                $this->field('type', 'string', required: true, default: InstitutionType::Masjid->value, allowedValues: $this->enumValues(InstitutionType::class)),
                $this->field('description', 'rich_text', required: false),
                $this->field('address', 'object', required: true),
                $this->field('address.country_id', 'integer', required: true, default: $preferredCountryId, catalog: route('api.client.catalogs.countries')),
                $this->field('contacts', 'array<object>', required: false),
                $this->field('social_media', 'array<object>', required: false),
                $this->field('cover', 'file', required: false),
                $this->field('gallery', 'array<file>', required: false),
            ],
            'conditional_rules' => [
                ['field' => 'address.google_maps_url', 'required' => true],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function submitSpeaker(): array
    {
        return [
            'flow' => 'submit_speaker',
            'method' => 'POST',
            'endpoint' => route('api.client.contributions.speakers.store'),
            'auth_required' => true,
            'defaults' => [
                'gender' => Gender::Male->value,
                'is_freelance' => false,
                'address' => [
                    'country_id' => app(PreferredCountryResolver::class)->resolveId(),
                    'state_id' => null,
                    'district_id' => null,
                    'subdistrict_id' => null,
                ],
            ],
            'fields' => [
                $this->field('name', 'string', required: true, maxLength: 255),
                $this->field('honorific', 'array<string>', required: false, allowedValues: $this->enumValues(Honorific::class)),
                $this->field('pre_nominal', 'array<string>', required: false, allowedValues: $this->enumValues(PreNominal::class)),
                $this->field('post_nominal', 'array<string>', required: false, allowedValues: $this->enumValues(PostNominal::class)),
                $this->field('gender', 'string', required: true, default: Gender::Male->value, allowedValues: $this->enumValues(Gender::class)),
                $this->field('is_freelance', 'boolean', required: false, default: false),
                $this->field('job_title', 'string', required: false, maxLength: 255),
                $this->field('bio', 'rich_text', required: false),
                $this->field('institution_id', 'uuid', required: false, catalog: route('api.client.catalogs.submit-institutions')),
                $this->field('institution_position', 'string', required: false, maxLength: 255),
                $this->field('address', 'object', required: true),
                $this->field('address.country_id', 'integer', required: true, catalog: route('api.client.catalogs.countries')),
                $this->field('address.state_id', 'integer', required: false),
                $this->field('address.district_id', 'integer', required: false),
                $this->field('address.subdistrict_id', 'integer', required: false),
                $this->field('qualifications', 'array<object>', required: false),
                $this->field('language_ids', 'array<int>', required: false, catalog: route('api.client.catalogs.languages')),
                $this->field('contacts', 'array<object>', required: false),
                $this->field('social_media', 'array<object>', required: false),
                $this->field('avatar', 'file', required: false),
                $this->field('cover', 'file', required: false),
                $this->field('gallery', 'array<file>', required: false),
            ],
            'conditional_rules' => [
                ['field' => 'job_title', 'required_when' => ['is_freelance' => [true]]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function membershipClaim(MemberSubjectType $subjectType): array
    {
        return [
            'flow' => 'membership_claim',
            'method' => 'POST',
            'endpoint_template' => route('api.client.membership-claims.store', [
                'subjectType' => $subjectType->publicRouteSegment(),
                'subject' => 'subject',
            ], false),
            'auth_required' => true,
            'fields' => [
                $this->field('justification', 'string', required: true, maxLength: 2000),
                $this->field('evidence', 'array<file>', required: true),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        return [
            'flow' => 'report',
            'method' => 'POST',
            'endpoint' => route('api.reports.store'),
            'auth_required' => true,
            'fields' => [
                $this->field('entity_type', 'string', required: true),
                $this->field('entity_id', 'uuid', required: true),
                $this->field('category', 'string', required: true),
                $this->field('description', 'string', required: false, maxLength: 2000),
            ],
            'conditional_rules' => [
                ['field' => 'description', 'required_when' => ['category' => ['other']]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function accountSettings(User $user): array
    {
        $mcpServers = $this->mcpTokenManager->availableServers($user);

        return [
            'flow' => 'account_settings',
            'method' => 'PUT',
            'endpoint' => route('api.client.account-settings.update'),
            'show_endpoint' => route('api.client.account-settings.show'),
            'auth_required' => true,
            'defaults' => [
                'name' => $user->name,
                'email' => (string) ($user->email ?? ''),
                'phone' => (string) ($user->phone ?? ''),
                'timezone' => (string) ($user->timezone ?? ''),
                'daily_prayer_institution_id' => (string) ($user->daily_prayer_institution_id ?? ''),
                'friday_prayer_institution_id' => (string) ($user->friday_prayer_institution_id ?? ''),
            ],
            'fields' => [
                $this->field('name', 'string', required: true, maxLength: 255),
                $this->field('email', 'email', required: false, maxLength: 255),
                $this->field('phone', 'string', required: false),
                $this->field('timezone', 'timezone', required: false),
                $this->field('daily_prayer_institution_id', 'uuid', required: false, catalog: route('api.client.catalogs.prayer-institutions')),
                $this->field('friday_prayer_institution_id', 'uuid', required: false, catalog: route('api.client.catalogs.prayer-institutions')),
            ],
            'conditional_rules' => [
                ['field' => 'email', 'required_when_missing' => ['phone']],
                ['field' => 'phone', 'required_when_missing' => ['email']],
            ],
            'notification_settings_endpoint' => route('api.notification-settings.show'),
            'notification_update_endpoint' => route('api.notification-settings.update'),
            'mcp_tokens_endpoint' => route('api.client.account-settings.mcp-tokens.index'),
            'mcp_token_store_endpoint' => route('api.client.account-settings.mcp-tokens.store'),
            'mcp_token_revoke_endpoint_template' => route('api.client.account-settings.mcp-tokens.destroy', ['tokenId' => 'token'], false),
            'mcp_token_fields' => [
                $this->field('name', 'string', required: true, maxLength: 255),
                $this->field('server', 'string', required: true, allowedValues: array_keys($mcpServers)),
            ],
            'mcp_servers' => $mcpServers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function advancedEvent(User $user): array
    {
        $builderContext = $this->catalogService->advancedBuilderContext($user);

        return [
            'flow' => 'advanced_event',
            'method' => 'POST',
            'endpoint' => route('api.client.advanced-events.store'),
            'auth_required' => true,
            'defaults' => $builderContext['default_form'],
            'fields' => [
                $this->field('title', 'string', required: true, maxLength: 255),
                $this->field('description', 'string', required: false),
                $this->field('timezone', 'timezone', required: true, default: 'Asia/Kuala_Lumpur'),
                $this->field('program_starts_at', 'datetime', required: true),
                $this->field('program_ends_at', 'datetime', required: true),
                $this->field('organizer_type', 'string', required: true, allowedValues: ['institution', 'speaker']),
                $this->field('organizer_id', 'uuid', required: true),
                $this->field('location_institution_id', 'uuid', required: false),
                $this->field('default_event_type', 'string', required: true, allowedValues: $this->enumValues(EventType::class)),
                $this->field('default_event_format', 'string', required: true, allowedValues: $this->enumValues(EventFormat::class)),
                $this->field('visibility', 'string', required: true, allowedValues: $this->enumValues(EventVisibility::class)),
                $this->field('registration_required', 'boolean', required: true),
                $this->field('registration_mode', 'string', required: true, allowedValues: $this->enumValues(RegistrationMode::class)),
            ],
            'options' => [
                'institution_options' => $builderContext['institution_options'],
                'speaker_options' => $builderContext['speaker_options'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function institutionWorkspace(): array
    {
        return [
            'flow' => 'institution_workspace',
            'workspace_endpoint' => route('api.client.institution-workspace.show'),
            'member_add_endpoint_template' => route('api.client.institution-workspace.members.store', ['institutionId' => 'institution'], false),
            'member_update_endpoint_template' => route('api.client.institution-workspace.members.update', ['institutionId' => 'institution', 'memberId' => 'member'], false),
            'member_remove_endpoint_template' => route('api.client.institution-workspace.members.destroy', ['institutionId' => 'institution', 'memberId' => 'member'], false),
            'member_add_fields' => [
                $this->field('email', 'email', required: true),
                $this->field('role_id', 'string', required: true, catalog: route('api.client.catalogs.institution-roles')),
            ],
            'member_edit_fields' => [
                $this->field('role_id', 'string', required: true, catalog: route('api.client.catalogs.institution-roles')),
            ],
        ];
    }

    /**
     * @param  class-string<\BackedEnum>  $enumClass
     * @return list<string|int>
     */
    private function enumValues(string $enumClass): array
    {
        return array_map(static fn (\BackedEnum $enum): string|int => $enum->value, $enumClass::cases());
    }

    /**
     * @param  list<string|int>|null  $allowedValues
     * @return array<string, mixed>
     */
    private function field(
        string $name,
        string $type,
        bool $required,
        mixed $default = null,
        ?int $maxLength = null,
        ?array $allowedValues = null,
        ?string $catalog = null,
    ): array {
        return array_filter([
            'name' => $name,
            'type' => $type,
            'required' => $required,
            'default' => $default,
            'max_length' => $maxLength,
            'allowed_values' => $allowedValues,
            'catalog' => $catalog,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
