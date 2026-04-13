<?php

declare(strict_types=1);

return [
    /*
     * Your API path. By default, all routes starting with this path will be added to the docs.
     * If you need to change this behavior, you can add your custom routes resolver using `Scramble::routes()`.
     */
    'api_path' => env('SCRAMBLE_API_PATH', 'api/v1'),

    /*
     * Your API domain. By default, app domain is used. This is also a part of the default API routes
     * matcher, so when implementing your own, make sure you use this config if needed.
     */
    'api_domain' => env('SCRAMBLE_API_DOMAIN'),

    /*
     * The path where your OpenAPI specification will be exported.
     */
    'export_path' => env('SCRAMBLE_EXPORT_PATH', 'docs/openapi.json'),

    'info' => [
        /*
         * API version.
         */
        'version' => env('API_VERSION', 'v1'),

        /*
         * Description rendered on the home page of the API documentation (`/docs`).
         */
        'description' => <<<'MD'
    Canonical API documentation for Majlis Ilmu client and platform integrations.

    Get an access token by calling POST /auth/login or POST /auth/register with a device_name, then send the returned access_token as Authorization: Bearer {token}. Existing users can also create and revoke personal access tokens from Account Settings > API Access inside the application.

    ROUTING SURFACES:

    Public routes (/api/v1/speakers, /api/v1/institutions, etc.) return only active and verified records (is_active=true AND status=verified).

    Admin routes (/api/v1/admin/speakers, etc.) return all records regardless of active or status state.

    The same search= parameter on both surfaces returns different result sets by design.

    Do not send public contribution payloads to /admin endpoints and do not expect admin schemas from /forms endpoints.

    TIMEZONE:

    All API timestamps are stored and returned in UTC (ISO 8601 with Z suffix).

    The server timezone is UTC; the default display timezone is Asia/Kuala_Lumpur (MYT, UTC+8).

    Date filter values such as filter[starts_after] and filter[starts_before] must be expressed in UTC.

    Example: MYT April 12 today = filter[starts_after]=2026-04-11T16:00:00Z&filter[starts_before]=2026-04-12T15:59:59Z.

    PUBLIC FLOWS:

    Public create flows currently exist for events, institutions, and speakers.

    Public update flows currently exist for events, institutions, speakers, and references via the contribution suggestion endpoints; those updates either apply immediately (mode=direct_edit) or create a review request (mode=review) depending on the caller's permissions.

    Public API support does not currently include creating references, venues, or series, and it does not currently include updating venues or series through the contribution endpoints.

    When you need required versus optional fields, defaults, catalog lookups, or conditional rules for a public mutation, fetch the corresponding GET /forms/* contract first.

    For public update suggestions specifically, fetch GET /forms/contributions/{subjectType}/{subject}/suggest first to get the current state, sparse editable fields, and direct-edit media capabilities.

    Public institution, speaker, and submit-event writes must include an explicit country selection. Canonical fields remain *_country_id, while *_country_code and *_country_key are accepted aliases.

    Speaker create/update still forbids detailed street or map fields: address.line1, address.line2, address.postcode, address.lat, address.lng, address.google_maps_url, address.google_place_id, and address.waze_url return HTTP 422 on speaker contribution flows.

    ADMIN FLOWS:

    Admin create and update flows are schema-driven: discover writable resources with GET /admin/manifest, then fetch the exact contract with GET /admin/{resourceKey}/schema?operation=create or GET /admin/{resourceKey}/schema?operation=update&recordKey={id}.

    The recordKey parameter must be the UUID primary key (the id field from collection or record-detail responses). Slugs (route_key) are not accepted as recordKey.

    Admin PUT is not a partial update. Fields marked required in the schema must be sent on every update, not just on create.

    For speakers: name, gender, and status are always required on both create and update.

    For institutions: name, type, and status are always required on both create and update.

    The allow_public_event_submission field is only accepted on PUT (update), not on POST (create).

    For admin geography lookups, use the authenticated GET /admin/catalogs/* endpoints referenced by schema catalog metadata.

    Current admin write support includes events, institutions, speakers, references, venues, and subdistricts.

    Admin write support is limited to resources whose write_support.schema flag is true in the admin manifest.
    MD,
    ],

    /*
     * Customize Stoplight Elements UI
     */
    'ui' => [
        /*
         * Define the title of the documentation's website. App name is used when this config is `null`.
         */
        'title' => env('SCRAMBLE_UI_TITLE', env('APP_NAME').' API'),

        /*
         * Define the theme of the documentation. Available options are `light`, `dark`, and `system`.
         */
        'theme' => 'light',

        /*
         * Hide the `Try It` feature. Enabled by default.
         */
        'hide_try_it' => false,

        /*
         * Hide the schemas in the Table of Contents. Enabled by default.
         */
        'hide_schemas' => false,

        /*
         * URL to an image that displays as a small square logo next to the title, above the table of contents.
         */
        'logo' => '',

        /*
         * Use to fetch the credential policy for the Try It feature. Options are: omit, include (default), and same-origin
         */
        'try_it_credentials_policy' => 'include',

        /*
         * There are three layouts for Elements:
         * - sidebar - (Elements default) Three-column design with a sidebar that can be resized.
         * - responsive - Like sidebar, except at small screen sizes it collapses the sidebar into a drawer that can be toggled open.
         * - stacked - Everything in a single column, making integrations with existing websites that have their own sidebar or other columns already.
         */
        'layout' => 'responsive',
    ],

    /*
     * The list of servers of the API. By default, when `null`, server URL will be created from
     * `scramble.api_path` and `scramble.api_domain` config variables. When providing an array, you
     * will need to specify the local server URL manually (if needed).
     *
     * Example of non-default config (final URLs are generated using Laravel `url` helper):
     *
     * ```php
     * 'servers' => [
     *     'Live' => 'api',
     *     'Prod' => 'https://scramble.dedoc.co/api',
     * ],
     * ```
     */
    'servers' => null,

    /**
     * Determines how Scramble stores the descriptions of enum cases.
     * Available options:
     * - 'description' – Case descriptions are stored as the enum schema's description using table formatting.
     * - 'extension' – Case descriptions are stored in the `x-enumDescriptions` enum schema extension.
     *
     *    @see https://redocly.com/docs-legacy/api-reference-docs/specification-extensions/x-enum-descriptions
     * - false - Case descriptions are ignored.
     */
    'enum_cases_description_strategy' => 'description',

    /**
     * Determines how Scramble stores the names of enum cases.
     * Available options:
     * - 'names' – Case names are stored in the `x-enumNames` enum schema extension.
     * - 'varnames' - Case names are stored in the `x-enum-varnames` enum schema extension.
     * - false - Case names are not stored.
     */
    'enum_cases_names_strategy' => false,

    /**
     * When Scramble encounters deep objects in query parameters, it flattens the parameters so the generated
     * OpenAPI document correctly describes the API. Flattening deep query parameters is relevant until
     * OpenAPI 3.2 is released and query string structure can be described properly.
     *
     * For example, this nested validation rule describes the object with `bar` property:
     * `['foo.bar' => ['required', 'int']]`.
     *
     * When `flatten_deep_query_parameters` is `true`, Scramble will document the parameter like so:
     * `{"name":"foo[bar]", "schema":{"type":"int"}, "required":true}`.
     *
     * When `flatten_deep_query_parameters` is `false`, Scramble will document the parameter like so:
     *  `{"name":"foo", "schema": {"type":"object", "properties":{"bar":{"type": "int"}}, "required": ["bar"]}, "required":true}`.
     */
    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
    ],

    'extensions' => [],
];
