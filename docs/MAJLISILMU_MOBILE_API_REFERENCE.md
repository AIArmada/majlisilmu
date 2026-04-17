# Majlisilmu Mobile API Reference

**Last Updated:** 2026-04-17
**Audience:** Android, iOS application developers, and AI agents
**Public Base Path:** `/api/v1`
**Admin Base Path:** `/api/v1/admin`

This is the current mobile-facing API contract. It reflects the live routes and controllers, including the Action-class refactors and the new native-client endpoints for auth tokens, going, registration, and check-in.

Use this document as the source of truth for mobile and AI agent integrations.

---

## AI Quickstart

If you are building an AI client, use this read order:

1. Fetch `/docs.json` first for the current OpenAPI contract.
2. Choose the correct routing surface: `/api/v1` for public and client behavior, `/api/v1/admin` for admin-only reads and writes.
3. Call `GET /manifest` for public workflow discovery or `GET /admin/manifest` for admin resource discovery.
4. Before any write, fetch the exact contract first: `GET /forms/*` for public flows, or `GET /admin/{resourceKey}/schema` for admin writes.
5. Use the admin record `route_key` returned by collection or detail payloads for record-specific schema and mutation paths. The legacy `id` remains accepted as a compatibility fallback.
6. Send raw timestamp fields in UTC. For date-only filters, send the user's local calendar date together with timezone context so the server can convert it to UTC boundaries.
7. Treat `error.code` as the machine-readable failure classifier and `meta.request_id` as the trace identifier for retries and support.

---

## Quick-reference: Routing Surfaces

This API has **two distinct routing surfaces**. Understanding the difference is critical before making any request.

| Surface | Base path | Auth | Audience | Record scope |
|---|---|---|---|---|
| **Public / Client** | `/api/v1` | Optional or Sanctum bearer | Mobile apps, public consumers, AI readers | Active + verified records only |
| **Admin** | `/api/v1/admin` | Sanctum bearer + Filament admin-panel access | Admin apps, operators, AI writers | All records (including inactive / unverified) |

Key routing rules:

- **Public query routes** (`/api/v1/speakers`, `/api/v1/institutions`, etc.) return only records where `is_active = true` AND `status = 'verified'`. They never return drafts or rejected records.
- **Admin routes** (`/api/v1/admin/speakers`, etc.) use Filament's own Eloquent query, which includes all records regardless of active or status state. The same `search=...` parameter on both surfaces therefore returns different result sets.
- **Admin mutation routes** (POST / PUT) use the resource key and the record's admin **route_key** for record-specific paths. The format is `/api/v1/admin/{resourceKey}/{recordKey}`. For slug-backed resources this is the slug; for UUID-backed resources it may still equal the primary key. The legacy `id` remains accepted as a compatibility fallback.
- Do not send public contribution payloads to `/api/v1/admin`, and do not expect admin schemas from `/api/v1/forms/...`.

---

## Timezone Semantics

**Raw API timestamp fields are stored and returned in UTC (ISO 8601 with `Z` suffix).**

| Context | Behavior |
|---|---|
| Raw API timestamps (`created_at`, `updated_at`, `starts_at`, `ends_at`, etc.) | Always UTC (`...Z`) |
| Viewer-facing helper fields (`timing_display`, `end_time_display`) | Localized only when the request provides timezone context; otherwise they fall back to UTC |
| Request timezone context | Authenticated user preference, `X-Timezone`, `user_timezone`, cookie, or session |
| Date-only inputs (`event_date`, `filter[starts_after]`, `filter[starts_before]`, etc.) | Interpreted in the resolved request timezone, then converted to UTC day boundaries for storage or querying |
| Application/browser defaults | The web client commonly supplies `Asia/Kuala_Lumpur`, but generic API consumers should not assume that default |

**Implications for API consumers:**

- When you receive `starts_at: "2026-04-12T00:00:00Z"`, that is midnight UTC, which is **08:00 MYT** on the same calendar date.
- Helper fields such as `timing_display` and `end_time_display` are localized only when the request provides timezone context. A bare API request with no `X-Timezone`, `user_timezone`, cookie, session, or authenticated user preference will see those helper fields in UTC.
- `filter[starts_after]`, `filter[starts_before]`, `filter[ends_after]`, and `filter[ends_before]` are date-boundary filters. Send date-only values in the user's local calendar together with timezone context, and the API will convert them to UTC boundaries internally.
- To fetch events on MYT April 12 ("today" in Malaysia), send `X-Timezone: Asia/Kuala_Lumpur` with `filter[starts_after]=2026-04-12&filter[starts_before]=2026-04-12`. If you omit timezone context, the same filter values are interpreted in UTC instead.
- The app config stores `timezone = UTC` for the server. The web application often supplies `Asia/Kuala_Lumpur` automatically, but standalone API consumers must send timezone context explicitly when they need localized helper fields or local-date filtering.
- Push notification payloads embed a `timezone` field (e.g., `"Asia/Kuala_Lumpur"`) so the server can localize scheduled reminders.

---

## Search Visibility Difference

The `search` parameter on public and admin surfaces queries different record scopes.

| Surface | Endpoint | Records returned |
|---|---|---|
| Public | `GET /api/v1/speakers?search=...` | Only `is_active = true` AND `status = 'verified'` |
| Public | `GET /api/v1/institutions?search=...` | Only `is_active = true` AND `status = 'verified'` |
| Admin | `GET /api/v1/admin/speakers?search=...` | **All** records — active, inactive, pending, rejected |
| Admin | `GET /api/v1/admin/institutions?search=...` | **All** records — active, inactive, pending, rejected |

This is intentional. The admin surface mirrors Filament's resource query, which does not apply visibility filters. A speaker that returns zero results on the public surface may appear on the admin surface because it is inactive or has `status = 'pending'`.

**AI agent guidance:** Never assume that a search result from one surface tells you anything definitive about results from the other. If you need to verify whether a speaker is visible to the public, check `is_active` and `status` in the record attributes.

---

## 1. Authentication

Authenticated endpoints use Laravel Sanctum bearer tokens.

Critical authorization rule:

- Bearer tokens identify the current user. They do not grant extra permissions beyond the user account behind the token.
- Public and admin authorization is recalculated from the live user state on every request.
- If you add or remove global roles, scoped roles, or direct permissions from a user, existing bearer tokens immediately reflect that change.
- Do not rely on Sanctum token abilities such as `tokenCan()` to predict whether a request will be allowed. The application policy and role system is the source of truth.

Required header:

```http
Authorization: Bearer <access_token>
Accept: application/json
```

### Token endpoints

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/auth/register` | Create a user account and issue a bearer token |
| `POST` | `/auth/login` | Issue a bearer token for an existing user |
| `POST` | `/auth/logout` | Revoke the current bearer token |
| `GET` | `/user` | Return the authenticated user |

### `POST /auth/register`

Request body:

```json
{
  "name": "Mobile User",
  "email": "mobile@example.com",
  "password": "password",
  "password_confirmation": "password",
  "device_name": "iPhone 15 Pro"
}
```

Notes:

- `email` or `phone` is required.
- `device_name` is required and is used as the Sanctum token name.
- Response includes `access_token`, `token_type`, and `user`.

### `POST /auth/login`

Request body:

```json
{
  "login": "mobile@example.com",
  "password": "password",
  "device_name": "Pixel 9"
}
```

Notes:

- `login` accepts either email or phone.
- Response includes `access_token`, `token_type`, and `user`.

Public vs admin routing:

- Public/mobile flows live under `/api/v1/...`.
- Admin-panel parity flows live under `/api/v1/admin/...`.
- Do not send public contribution payloads to `/api/v1/admin`, and do not expect admin schemas from `/api/v1/forms/...`.
- See the **Quick-reference: Routing Surfaces** section at the top of this document for a full comparison of the two surfaces.

---

## 2. Response Conventions

Most endpoints return:

```json
{
  "data": {},
  "meta": {
    "request_id": "uuid"
  }
}
```

Mutation endpoints may also include a top-level `message`.

For backward compatibility, some mutation endpoints may also mirror that same success text at `data.message`. New clients should prefer the top-level `message`.

Application and domain errors return a stable top-level `error` envelope:

```json
{
  "message": "The requested resource could not be found.",
  "error": {
    "code": "not_found",
    "message": "The requested resource could not be found."
  },
  "meta": {
    "request_id": "uuid"
  }
}
```

Paginated list endpoints generally return:

```json
{
  "data": [],
  "meta": {
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total": 120
    }
  }
}
```

`meta.pagination` is the canonical pagination bag for new clients. Some list endpoints may still retain the native Laravel paginator keys such as `current_page`, `per_page`, `total`, and `links` at the top level for backward compatibility.

List endpoints clamp `per_page` to server-supported maxima. Public `/events`, `/institutions`, and `/speakers` currently cap at 50. Most authenticated collections and admin resource listings currently cap at 100.

Validation failures use the same `error` envelope with a field-level bag preserved for direct form binding:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "The email field is required."
    ]
  },
  "error": {
    "code": "validation_error",
    "message": "The given data was invalid.",
    "details": {
      "fields": {
        "email": [
          "The email field is required."
        ]
      }
    }
  },
  "meta": {
    "request_id": "uuid"
  }
}
```

---

## 2A. Client Parity API

For native clients and AI agents that need to mirror the current web client behavior rather than the lower-level REST resources, use the high-level client surface under `/api/v1`.

Interactive API docs are available on the API host under `/docs`, with the generated OpenAPI JSON published at `/docs.json`.

### Discovery and contracts

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `GET` | `/manifest` | Optional | Top-level flow manifest and endpoint discovery |
| `GET` | `/forms/submit-event` | Optional | Submit-event schema, defaults, and validation metadata |
| `GET` | `/forms/contributions/institutions` | Optional | Institution contribution contract |
| `GET` | `/forms/contributions/speakers` | Optional | Speaker contribution contract |
| `GET` | `/forms/report` | Required | Report form contract |
| `GET` | `/forms/account-settings` | Required | Account-settings contract |
| `GET` | `/forms/advanced-events` | Required | Advanced parent-program builder contract |
| `GET` | `/forms/institution-workspace` | Required | Institution workspace contract |
| `GET` | `/forms/membership-claims/{subjectType}` | Required | Membership-claim contract for the selected subject type |
| `GET` | `/forms/contributions/{subjectType}/{subject}/suggest` | Required | Suggest-update context and editable state |

### Public query endpoints

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/search` | Unified search payload for events, speakers, and institutions |
| `GET` | `/institutions` | Public institution listing filters |
| `GET` | `/institutions/{institutionKey}` | Public institution detail by slug or UUID |
| `GET` | `/speakers` | Public speaker listing filters |
| `GET` | `/speakers/{speakerKey}` | Public speaker detail by slug or UUID |
| `GET` | `/inspirations/random` | Random active inspiration payload with category and media metadata |
| `GET` | `/venues/{venueKey}` | Public venue detail by slug or UUID |
| `GET` | `/references/{referenceKey}` | Public reference detail by slug or UUID |
| `GET` | `/series/{series}` | Public series detail |

Notes:

- **Visibility rule:** `/speakers` and `/institutions` return **only** records where `is_active = true` AND `status = 'verified'`. Inactive or unverified records are invisible on the public surface. To access all records including drafts, use the admin surface.
- These detail payloads now mirror the web client media collections and public-contact visibility rules.
- Institution payloads now expose `public_image_url` as the canonical cover -> logo -> placeholder image. Use that for cards and previews unless you explicitly need the raw compatibility aliases (`image_url`, `logo_url`, `cover_url`, `chip_image_url`).
- The inspiration endpoint returns `title`, plain-text `content`, `content_html`, `preview_text`, `source`, category metadata, and both thumb/full media URLs when an image exists.
- `speakerKey`, `venueKey`, `institutionKey`, and `referenceKey` intentionally bypass the app-wide public-slug route binders so the API can safely resolve slug or UUID itself.
- `GET /catalogs/spaces` returns only global spaces when `institution_id` is omitted. When `institution_id` is provided, the response includes those global spaces plus spaces linked to the selected institution.

### Submission and authenticated workflow endpoints

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/submit-event` | Submit the public/authenticated event form, including poster/gallery uploads |
| `GET` | `/account-settings` | Return current profile settings |
| `PUT` | `/account-settings` | Update current profile settings |
| `GET` | `/contributions` | Contribution inbox for the current user |
| `POST` | `/contributions/institutions` | Submit a new institution contribution |
| `POST` | `/contributions/speakers` | Submit a new speaker contribution |
| `POST` | `/contributions/{subjectType}/{subject}/suggest` | Suggest an update or apply a direct edit when authorized |
| `POST` | `/contributions/{requestId}/approve` | Approve a reviewable contribution request |
| `POST` | `/contributions/{requestId}/reject` | Reject a reviewable contribution request |
| `POST` | `/contributions/{requestId}/cancel` | Cancel the current user’s pending contribution request |
| `GET` | `/membership-claims` | List the current user’s membership claims |
| `POST` | `/membership-claims/{subjectType}/{subject}` | Submit a membership claim with evidence uploads |
| `DELETE` | `/membership-claims/{claimId}` | Cancel the current user’s pending membership claim |
| `POST` | `/advanced-events` | Create an advanced parent program submission |
| `GET` | `/follows/{type}/{subject}` | Return follow state for the current user |
| `POST` | `/follows/{type}/{subject}` | Follow a public institution, speaker, reference, or series |
| `DELETE` | `/follows/{type}/{subject}` | Unfollow a record |
| `GET` | `/institution-workspace` | Institution dashboard payload for events, members, and role options |
| `POST` | `/institution-workspace/{institutionId}/members` | Add an institution member |
| `PUT` | `/institution-workspace/{institutionId}/members/{memberId}` | Change an institution member role |
| `DELETE` | `/institution-workspace/{institutionId}/members/{memberId}` | Remove an institution member |

Authorization note:

- These endpoints intentionally mirror the current web client policy and role checks.
- `can_direct_edit`, `direct_edit_media_fields`, institution workspace access, and approval abilities all come from live policy and role evaluation for the authenticated user, not from bearer-token abilities.
- Institution workspace permissions currently follow the existing app model where scoped member roles are assigned per subject type, not per individual institution. The API matches that behavior exactly.
- `GET /institution-workspace` auto-selects the first accessible institution when `institution_id` is omitted and always returns a non-null `selected_institution` block together with `events_pagination` and `members_pagination` metadata.

### Public contribution rules you must follow

#### `GET /forms/contributions/institutions`

- Use this as the authoritative create contract for public institution submissions.
- Institution create requires an explicit address country.
- You may send one of:
  - `address.country_id`
  - `address.country_code`
  - `address.country_key`
- If the same normalized institution name plus the same `state_id`, `district_id`, and `subdistrict_id` already exists, create will fail with HTTP `422` on `name`.

#### `GET /forms/contributions/speakers`

- Use this as the authoritative create contract for public speaker submissions.
- Speaker create requires an explicit country plus region selectors. Send:
  - one of `address.country_id`, `address.country_code`, or `address.country_key`
  - `address.state_id`
  - `address.district_id`
  - `address.subdistrict_id`
- Do not send:
  - `address.line1`
  - `address.line2`
  - `address.postcode`
  - `address.lat`
  - `address.lng`
  - `address.google_maps_url`
  - `address.google_place_id`
  - `address.waze_url`
- Public speaker create accepts media fields `avatar`, `cover`, and `gallery`.
- If the same normalized speaker identity already exists in the same country, create will fail with HTTP `422` on `name`. The duplicate check compares normalized `name`, `gender`, `honorific`, `pre_nominal`, effective `post_nominal`, and country values.

#### `GET /forms/contributions/{subjectType}/{subject}/suggest`

- Call this before every update screen.
- `can_direct_edit = true` means the caller can apply changes immediately.
- `can_direct_edit = false` means the same endpoint will create a review request instead.
- `direct_edit_media_fields` is the only allowed file-upload contract for direct edits.
- Current direct-edit media sets are:
  - Institutions: `['cover']`
  - Speakers: `['avatar', 'cover']`
  - Everyone else or non-maintainers: `[]`

#### `POST /contributions/{subjectType}/{subject}/suggest`

- This endpoint is sparse-update only. Send only changed top-level keys plus optional `proposer_note`.
- File uploads are allowed only when `can_direct_edit = true` and only for field names listed in `direct_edit_media_fields`.
- Unsupported upload fields return HTTP `422` with a `files` validation error.
- Direct-edit response mode:

```json
{
  "data": {
    "mode": "direct_edit"
  }
}
```

- Review-request response mode:

```json
{
  "data": {
    "mode": "review"
  }
}
```

---

## 2B. Admin API Foundation

For authenticated users who can access the Filament admin panel, an admin surface is available under `/api/v1/admin`. This follows Filament resource discovery and authorization at the resource/record level, and is intended for admin apps and agents that need to browse and, for selected resources, mutate what the admin panel can currently expose.

Current scope:

- Resource manifest and capability discovery
- Per-resource metadata
- Resource-specific write schema discovery for supported resources
- Generic record listing with search
- Generic record detail with per-record abilities
- Shared create/update write support for `speakers`, `institutions`, `venues`, `references`, `events`, and `subdistricts`

Current limitation:

- This is not yet full create-edit-delete parity for every complex Filament workflow. Write support currently exists only where the Filament save path has been extracted into reusable actions.

### Admin endpoints

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `GET` | `/admin/manifest` | Required Filament admin-panel access | List admin resources visible through the API |
| `GET` | `/admin/{resourceKey}/meta` | Required Filament admin-panel access | Return metadata, pages, relations, abilities, and write-support flags for one admin resource |
| `GET` | `/admin/{resourceKey}/schema?operation=create` | Required Filament admin-panel access | Return the create contract for supported write resources |
| `GET` | `/admin/{resourceKey}/schema?operation=update&recordKey={recordKey}` | Required Filament admin-panel access | Return the update contract plus current defaults/media for one supported record |
| `GET` | `/admin/{resourceKey}` | Required Filament admin-panel access | Paginated record listing for the selected resource |
| `POST` | `/admin/{resourceKey}` | Required Filament admin-panel access + resource create policy | Create a record for supported write resources |
| `GET` | `/admin/{resourceKey}/{recordKey}` | Required Filament admin-panel access | Generic record detail and per-record abilities |
| `PUT` | `/admin/{resourceKey}/{recordKey}` | Required Filament admin-panel access + record update policy | Update a record for supported write resources |

> **Record-key format:** `{recordKey}` in GET and PUT admin record routes should use the `route_key` field returned by the admin collection or record-detail endpoints. For slug-backed resources this is the slug; for UUID-backed resources it may still equal the primary key. The legacy `id` remains accepted as a compatibility fallback, but new clients should standardize on `route_key`.

Authorization note:

- The admin API now follows the same top-level access rule as the Filament admin panel: any authenticated user with application admin-panel access can reach it.
- Bearer token abilities do not elevate a non-admin user into this surface.
- Within that surface, per-resource create/view and per-record update/view/delete abilities are still computed from the underlying Laravel policies, so the payload advertises what the current user can actually do.
- For `speakers` and `institutions`, the API write path now reuses the same save actions as the Filament create/edit pages, including address/contact/social sync, media handling, and public-submission toggle rules.
- Slugs for these write-capable resources are treated as auto-managed by the API contract. Clients should not attempt to persist custom slugs through these endpoints.

### Schema endpoint — key behaviors

The `GET /admin/{resourceKey}/schema` endpoint is the authoritative source for what fields are required and allowed for any given mutation.

```
GET /api/v1/admin/speakers/schema?operation=create
GET /api/v1/admin/speakers/schema?operation=update&recordKey=ahmad-fauzi-my
```

Rules:

- `recordKey` should use the record `route_key` returned by the admin collection or record-detail payload. For resources whose route key remains the UUID primary key, `route_key` and `id` are the same value. The legacy `id` remains accepted as a compatibility fallback.
- The schema response embeds an `endpoint` field with the exact URL you should POST or PUT to — use that directly.
- The schema response also embeds `defaults` with current field values, and `current_media` with existing media URLs, enabling pre-population of edit forms.
- The `method` field tells you whether to use `POST` or `PUT`.
- `conditional_rules` describe fields that become required based on other field values (e.g., `job_title` is required when `is_freelance = true` for speakers).

### Always-required fields for admin speaker write operations

Speaker `PUT` (update) is **not** a partial/PATCH operation. The following fields are always required on every create **and** update:

| Field | Type | Notes |
|---|---|---|
| `name` | `string` | Required on both create and update |
| `gender` | `string` | `male` or `female`. Required on both create and update |
| `status` | `string` | `pending`, `verified`, or `rejected`. Required on both create and update |
| `address` | `object` | Must be present on create (empty object `{}` is valid); optional on update, but must be a valid object if sent |

The `bio`, `qualifications`, `honorific`, `pre_nominal`, `post_nominal`, `language_ids`, `contacts`, `social_media`, and media fields are all optional (`nullable` / `sometimes`) on both operations.

To avoid unexpected `422` errors, always fetch the schema first and mirror the `required: true` fields verbatim.

### Always-required fields for admin institution write operations

Institution `PUT` is also a full-replacement PUT for core identity fields. The following are always required:

| Field | Type | Notes |
|---|---|---|
| `name` | `string` | Required on both create and update |
| `type` | `string` | Required on both create and update |
| `status` | `string` | `unverified`, `pending`, `verified`, or `rejected`. Required on both create and update |
| `address.country_id` | `integer` | Canonical country field. Required unless `address.country_code` or `address.country_key` is provided |

### Admin write-contract rules you must follow

- Always fetch `/admin/{resourceKey}/schema` before `POST` or `PUT`.
- Treat the returned schema as authoritative for allowed and prohibited fields.
- For `speakers`, admin write contracts now require the same explicit country plus region address model as the public speaker flows.
- Admin speaker create/update clients may send one of `address.country_id`, `address.country_code`, or `address.country_key`.
- Admin speaker create/update clients must not send:
  - `address.line1`
  - `address.line2`
  - `address.postcode`
  - `address.lat`
  - `address.lng`
  - `address.google_maps_url`
  - `address.google_place_id`
  - `address.waze_url`
- Admin speaker clients should send the explicit country field plus regional location keys such as `address.state_id`, `address.district_id`, and `address.subdistrict_id`.
- The `allow_public_event_submission` field is only accepted on `PUT` (update), not on `POST` (create). Sending it on create returns `422`.

### Example: full admin speaker create/update flow

```http
# 1. Discover the resource
GET /api/v1/admin/manifest

# 2. Get the create schema
GET /api/v1/admin/speakers/schema?operation=create

# 3. Create
POST /api/v1/admin/speakers
Content-Type: multipart/form-data

name=Ahmad Fauzi
gender=male
status=verified
is_freelance=false
is_active=true
address[state_id]=14

# 4. Note the returned admin route_key
# { "data": { "record": { "id": "0195b86a-3c15-73fa-a2d8-5a45f6a7f701", "route_key": "ahmad-fauzi-my", ... } } }

# 5. Get the update schema using the route_key returned by the record payload
GET /api/v1/admin/speakers/schema?operation=update&recordKey=ahmad-fauzi-my

# 6. Update — name, gender, status are always required
PUT /api/v1/admin/speakers/ahmad-fauzi-my
Content-Type: multipart/form-data

name=Ahmad Fauzi bin Abdullah
gender=male
status=verified
```

---

## 3. Event Discovery

### Public endpoints

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/events` | Event index with filters, includes, and sorting |
| `GET` | `/events/{eventOrSlug}` | Event detail by UUID or slug |

### `GET /events`

> **Timezone note:** `filter[starts_after]`, `filter[starts_before]`, `filter[ends_after]`, and `filter[ends_before]` are date-boundary filters. Send date-only values in the user's local calendar and provide timezone context via `X-Timezone` or `user_timezone`; the API converts them to UTC start/end-of-day boundaries. If you omit timezone context, these filters default to UTC.

Supported filters:

- `filter[status]`
- `filter[visibility]`
- `filter[event_format]`
- `filter[institution_id]`
- `filter[venue_id]`
- `filter[event_type]`
- `filter[starts_after]`
- `filter[starts_before]`
- `filter[ends_after]`
- `filter[ends_before]`
- `filter[state_id]`
- `filter[district_id]`
- `filter[subdistrict_id]`
- `filter[city_id]`
- `filter[speaker]`
- `filter[key_person_roles]`
- `filter[moderator_ids]`
- `filter[imam_ids]`
- `filter[khatib_ids]`
- `filter[bilal_ids]`
- `filter[series]`
- `filter[search]`
- `filter[prayer_time]`

Supported includes:

- `venue`
- `venue.address`
- `venue.address.state`
- `venue.address.district`
- `venue.address.subdistrict`
- `venue.address.city`
- `institution`
- `institution.address`
- `keyPeople`
- `keyPeople.speaker`
- `speakers`
- `series`
- `mediaLinks`
- `settings`
- `languages`
- `address`
- `address.state`
- `address.district`
- `address.subdistrict`
- `address.city`
- `donationChannels` on detail requests

Supported sorts:

- `title`
- `starts_at`
- `ends_at`
- `created_at`
- `updated_at`
- `views_count`

Example:

```http
GET /api/v1/events?filter[search]=kuliah&filter[state_id]=10&include=institution,venue,speakers,settings&sort=starts_at
```

Mobile recommendation:

- For event detail screens, request `institution`, `venue.address`, `speakers`, `settings`, `languages`, and `donationChannels` when needed.

---

## 4. Event Engagement

### Saved events

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/event-saves` | List saved events for the authenticated user |
| `POST` | `/event-saves` | Save an event |
| `GET` | `/event-saves/{eventId}` | Check if the event is saved |
| `DELETE` | `/event-saves/{eventId}` | Remove a saved event |

Request body for `POST /event-saves`:

```json
{
  "event_id": "uuid"
}
```

### Going

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/user/going-events` | List events the authenticated user marked as going |
| `GET` | `/events/{event}/going` | Check if the user marked the event as going |
| `POST` | `/events/{event}/going` | Mark the event as going |
| `DELETE` | `/events/{event}/going` | Remove the going record |

Notes:

- `POST /events/{event}/going` is restricted to public, engageable, future events.
- Response returns the updated `going_count`.

---

## 5. Registrations And Check-In

### Registrations

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `POST` | `/events/{event}/registrations` | Optional | Register for an event |
| `GET` | `/events/{event}/registration-status` | Required | Return the authenticated user’s registration state |
| `GET` | `/user/registrations` | Required | List the authenticated user’s registrations |
| `GET` | `/events/{event}/registrations/export` | Required | Export event registrations as CSV if authorized |

Registration request body:

```json
{
  "name": "Registrant Name",
  "email": "registrant@example.com",
  "phone": "+60123456789"
}
```

Notes:

- Guests must provide either `email` or `phone`.
- Authenticated users may register without `email` or `phone`.
- Registration follows the same `RegisterForEventAction` rules as the web route.
- Unlisted events remain registerable when registration is enabled, matching the existing web behavior.

### Check-in

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/events/{event}/check-in-state` | Return current user check-in availability |
| `POST` | `/events/{event}/check-ins` | Record a check-in for the authenticated user |

`GET /events/{event}/check-in-state` returns:

```json
{
  "data": {
    "is_checked_in": false,
    "available": true,
    "reason": null,
    "method": "self_reported",
    "registration_id": null
  }
}
```

Notes:

- Check-in uses the shared `ResolveEventCheckInStateAction`, so API and web eligibility rules now match.
- If the event requires registration, the check-in state returns `registered_self_checkin` and the relevant `registration_id`.
- Duplicate check-ins return HTTP `200` with `data.status = "duplicate"` instead of creating another row.

---

## 6. Saved Searches

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/saved-searches` | List saved searches |
| `POST` | `/saved-searches` | Create a saved search |
| `GET` | `/saved-searches/{savedSearch}` | Show one saved search |
| `PUT` / `PATCH` | `/saved-searches/{savedSearch}` | Update a saved search |
| `DELETE` | `/saved-searches/{savedSearch}` | Delete a saved search |
| `POST` | `/saved-searches/{savedSearch}/execute` | Execute the saved search and return event results |

Selected fields:

- `name`
- `query`
- `filters`
- `radius_km`
- `lat`
- `lng`
- `notify`

---

## 7. Reports

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/reports` | Submit a report against an event, institution, speaker, venue, or reference |

Auth:

- Authentication is required.
- Rate limiting uses `throttle:reports`.

Request body:

```json
{
  "entity_type": "event",
  "entity_id": "uuid",
  "category": "wrong_info",
  "description": "Optional text when category is other."
}
```

Notes:

- Duplicate reports from the same reporter fingerprint within 24 hours return `409`.
- High-risk event reports can trigger re-moderation.

---

## 8. Notifications

### Inbox and settings

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/notifications` | List inbox-visible notifications |
| `POST` | `/notifications/{message}/read` | Mark one notification as read |
| `POST` | `/notifications/read-all` | Mark all inbox-visible notifications as read |
| `GET` | `/notification-settings/catalog` | Return notification families, triggers, and selectable options |
| `GET` | `/notification-settings` | Return the user’s current notification state |
| `PUT` | `/notification-settings` | Update notification settings |

Useful query params for `GET /notifications`:

- `family`
- `status=unread|read|all`
- `per_page`

### Push destinations

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/notification-destinations/push` | Register a push destination |
| `PUT` | `/notification-destinations/push/{installation}` | Update a push destination |
| `DELETE` | `/notification-destinations/push/{installation}` | Remove a push destination |

Push registration body:

```json
{
  "installation_id": "ios-primary",
  "platform": "ios",
  "fcm_token": "token-value",
  "app_version": "1.0.0",
  "device_label": "Aiman iPhone",
  "locale": "ms",
  "timezone": "Asia/Kuala_Lumpur"
}
```

Notes:

- Supported `platform` values are `ios` and `android`.
- This is the current native push registration surface for mobile apps.

---

## 9. Mobile Integration Notes

Recommended event detail fetch sequence:

1. `GET /events/{eventOrSlug}` with the includes required for the screen.
2. If authenticated, fetch in parallel:
   - `GET /event-saves/{eventId}`
   - `GET /events/{event}/going`
   - `GET /events/{event}/registration-status`
   - `GET /events/{event}/check-in-state`

Recommended auth flow:

1. Use `POST /auth/register` or `POST /auth/login`.
2. Persist the bearer token securely on-device.
3. Send `Authorization: Bearer <token>` on authenticated requests.
4. Use `POST /auth/logout` when explicitly signing out that device session.

---

## 10. Current Gaps To Be Aware Of

- Event saves still use the older collection-style endpoints (`/event-saves`) rather than nested event routes.
- There is no single `/events/{event}/me` aggregate endpoint yet; current mobile clients should fetch per-feature state in parallel.
- Registration export is CSV-only and intended for operator workflows rather than general attendee-facing mobile screens.

---

## 11. AI Agent Quick-Reference

This section summarizes the non-obvious rules that AI agents must internalize before calling this API.

### Record key rules

| Use case | Field to use |
|---|---|
| Read a record detail via admin API | `route_key` preferred, `id` accepted as a compatibility fallback |
| Fetch write schema (`?operation=update&recordKey=...`) | `route_key` preferred, `id` accepted as a compatibility fallback |
| PUT to update a record via admin API | `route_key` preferred in `/admin/{resourceKey}/{recordKey}`; `id` remains accepted as a compatibility fallback |
| Resolve public speaker/institution | Slug or UUID both accepted (`/speakers/{speakerKey}`) |

> The `route_key` field in admin API responses is the canonical record-specific path key for new clients. For resources that still use the UUID primary key as their route key, `route_key` and `id` are the same value. The legacy `id` remains accepted as a compatibility fallback on admin record and schema routes.

### Field requirement rules

| Resource | Always required (create AND update) | Update-only optional |
|---|---|---|
| Speaker | `name`, `gender`, `status` | `allow_public_event_submission` |
| Institution | `name`, `type`, `status` | `allow_public_event_submission` |
| Venue | `name`, `type`, `status` | — |
| Reference | `title`, `type`, `status` | — |
| Event | `title`, `event_date`, `prayer_time`, `timezone`, `event_format`, `visibility`, `gender`, `age_group`, `event_type` | — |

> Admin PUT is a full-field replacement for required fields. Always send all `required: true` fields from the schema even when updating.

### Search result scope

| Surface | Returns |
|---|---|
| `GET /api/v1/speakers?search=` | Active + verified speakers only |
| `GET /api/v1/institutions?search=` | Active + verified institutions only |
| `GET /api/v1/admin/speakers?search=` | All speakers (any status, active or inactive) |
| `GET /api/v1/admin/institutions?search=` | All institutions (any status, active or inactive) |

### Timestamp interpretation

All timestamps in API responses end in `Z` (UTC). To display "local time" for Malaysian users, add 8 hours (`UTC+8 = MYT`). When building date filters, always convert MYT user intent to UTC boundaries before sending.

### Prohibited address fields for speakers

Never send any of the following for speaker create or update (both public contribution and admin):
`address.line1`, `address.line2`, `address.postcode`, `address.lat`, `address.lng`, `address.google_maps_url`, `address.google_place_id`, `address.waze_url`

The server will reject them with HTTP `422`. Send an explicit country via `address.country_id`, `address.country_code`, or `address.country_key`, then optionally add `address.state_id`, `address.district_id`, and `address.subdistrict_id`.
