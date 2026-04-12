# Majlisilmu Mobile API Reference

**Last Updated:** 2026-04-12
**Audience:** Android, iOS application developers, and AI agents
**Public Base Path:** `/api/v1`
**Admin Base Path:** `/api/v1/admin`

This is the current mobile-facing API contract. It reflects the live routes and controllers, including the Action-class refactors and the new native-client endpoints for auth tokens, going, registration, and check-in.

Use this document as the source of truth for mobile and AI agent integrations.

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
- **Admin mutation routes** (POST / PUT) use the resource key and the record's **UUID primary key** (`id` field), never a slug. The format is `/api/v1/admin/{resourceKey}/{id}`. The `id` field is returned by the admin collection or record-detail endpoints; the `route_key` field is the human-readable slug and must not be used in mutation paths.
- Do not send public contribution payloads to `/api/v1/admin`, and do not expect admin schemas from `/api/v1/forms/...`.

---

## Timezone Semantics

**All API timestamps are stored and returned in UTC (ISO 8601 with `Z` suffix).**

| Context | Timezone |
|---|---|
| All API timestamps (`created_at`, `updated_at`, `starts_at`, `ends_at`, etc.) | UTC (`...Z`) |
| UI display (web and mobile defaults) | MYT — UTC+8 (Asia/Kuala_Lumpur) |
| Event `event_date` field (submitted/stored) | UTC date string (no time component) |

**Implications for API consumers:**

- When you receive `starts_at: "2026-04-12T00:00:00Z"`, that is midnight UTC, which is **08:00 MYT** on the same calendar date.
- "Today" and "tomorrow" filtering using `filter[starts_after]` and `filter[starts_before]` must be expressed as UTC boundaries. For example, to fetch events on MYT April 12 ("today" in Malaysia), query `filter[starts_after]=2026-04-11T16:00:00Z&filter[starts_before]=2026-04-12T15:59:59Z` — that is, MYT midnight (00:00 MYT) maps to 16:00 UTC the previous calendar day.
- The app config stores `timezone = UTC` for the server, and `default_user_timezone = Asia/Kuala_Lumpur` for UI rendering. AI agents and background jobs must translate between these when building date filters.
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

The event index uses Laravel paginator JSON, so it returns `data`, `links`, and `meta`.

Validation failures return Laravel JSON validation responses with HTTP `422`.

---

## 2A. Client Parity API

For native clients and AI agents that need to mirror the current web client behavior rather than the lower-level REST resources, use the high-level client surface under `/api/v1`.

Interactive local API docs are available at [https://majlisilmu.test/docs](https://majlisilmu.test/docs), with the generated OpenAPI JSON at [https://majlisilmu.test/docs.json](https://majlisilmu.test/docs.json).

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

### Public contribution rules you must follow

#### `GET /forms/contributions/institutions`

- Use this as the authoritative create contract for public institution submissions.
- Institution create still accepts a full address payload, including `address.country_id`.
- If the same normalized institution name plus the same `state_id`, `district_id`, and `subdistrict_id` already exists, create will fail with HTTP `422` on `name`.

#### `GET /forms/contributions/speakers`

- Use this as the authoritative create contract for public speaker submissions.
- Speaker create is region-only. Send only:
  - `address.state_id`
  - `address.district_id`
  - `address.subdistrict_id`
- Do not send:
  - `address.country_id`
  - `address.line1`
  - `address.line2`
  - `address.postcode`
  - `address.lat`
  - `address.lng`
  - `address.google_maps_url`
  - `address.google_place_id`
  - `address.waze_url`
- The server infers the speaker country from the active public country scope.
- Public speaker create accepts media fields `avatar`, `cover`, and `gallery`.
- If the same normalized speaker identity already exists, create will fail with HTTP `422` on `name`. The duplicate check compares normalized `name`, `gender`, `honorific`, `pre_nominal`, and effective `post_nominal` values.

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
| `GET` | `/admin/{resourceKey}/schema?operation=update&recordKey={recordUuid}` | Required Filament admin-panel access | Return the update contract plus current defaults/media for one supported record |
| `GET` | `/admin/{resourceKey}` | Required Filament admin-panel access | Paginated record listing for the selected resource |
| `POST` | `/admin/{resourceKey}` | Required Filament admin-panel access + resource create policy | Create a record for supported write resources |
| `GET` | `/admin/{resourceKey}/{id}` | Required Filament admin-panel access | Generic record detail and per-record abilities |
| `PUT` | `/admin/{resourceKey}/{id}` | Required Filament admin-panel access + record update policy | Update a record for supported write resources |

> **Route key format:** `{id}` in PUT and GET record routes is always the **UUID primary key** (`id` field) returned by the collection or record-detail endpoints. Using a slug here will cause a `404` in most cases unless the slug happens to also be a valid UUID. The `route_key` field in the response is the human-readable slug; `id` is the mutation key.

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
GET /api/v1/admin/speakers/schema?operation=update&recordKey=0195b86a-3c15-73fa-a2d8-5a45f6a7f701
```

Rules:

- `recordKey` **must be the UUID primary key** (`id`) of the existing record. The slug (`route_key`) is not accepted here.
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
| `address.country_id` | `integer` | Required on create; `sometimes` on update |

### Admin write-contract rules you must follow

- Always fetch `/admin/{resourceKey}/schema` before `POST` or `PUT`.
- Treat the returned schema as authoritative for allowed and prohibited fields.
- For `speakers`, admin write contracts now use the same region-only address model as the public speaker flows.
- Admin speaker create/update clients must not send:
  - `address.country_id`
  - `address.line1`
  - `address.line2`
  - `address.postcode`
  - `address.lat`
  - `address.lng`
  - `address.google_maps_url`
  - `address.google_place_id`
  - `address.waze_url`
- Admin speaker clients should send only regional location keys such as `address.state_id`, `address.district_id`, and `address.subdistrict_id`.
- The server resolves the effective speaker country from the existing record or the active preferred country scope.
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

# 4. Note the returned record id (UUID primary key)
# { "data": { "record": { "id": "0195b86a-3c15-73fa-a2d8-5a45f6a7f701", "route_key": "ahmad-fauzi-my", ... } } }

# 5. Get the update schema using the id (UUID primary key, not the slug)
GET /api/v1/admin/speakers/schema?operation=update&recordKey=0195b86a-3c15-73fa-a2d8-5a45f6a7f701

# 6. Update — name, gender, status are always required
PUT /api/v1/admin/speakers/0195b86a-3c15-73fa-a2d8-5a45f6a7f701
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

> **Timezone note:** All date/time filter values must be expressed in UTC. `filter[starts_after]=2026-04-12T00:00:00Z` means midnight UTC, which is 08:00 MYT. When building "today" or "this week" filters for Malaysian users, offset your boundaries by +8 hours relative to UTC midnight.

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
| Read a record detail via admin API | `id` (UUID primary key) or `route_key` (slug) — both accepted in the path |
| Fetch write schema (`?operation=update&recordKey=...`) | **`id` (UUID primary key only)** — slugs are not accepted here |
| PUT to update a record via admin API | **`id` (UUID primary key) in path** (`/admin/{resourceKey}/{id}`) |
| Resolve public speaker/institution | Slug or UUID both accepted (`/speakers/{speakerKey}`) |

> The `id` field in API responses is always the UUID primary key. The `route_key` field is the human-readable slug. For any mutation or schema fetch, always use `id`.

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
`address.country_id`, `address.line1`, `address.line2`, `address.postcode`, `address.lat`, `address.lng`, `address.google_maps_url`, `address.google_place_id`, `address.waze_url`

The server will reject them with HTTP `422`. Send only: `address.state_id`, `address.district_id`, `address.subdistrict_id`.
