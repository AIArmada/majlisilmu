# Majlisilmu Mobile API Reference

**Last Updated:** 2026-03-15  
**Audience:** Android and iOS application developers  
**Base Path:** `/api/v1`

This is the current mobile-facing API contract. It reflects the live routes and controllers, including the Action-class refactors and the new native-client endpoints for auth tokens, going, registration, and check-in.

Use this document as the source of truth for mobile integrations.

---

## 1. Authentication

Authenticated endpoints use Laravel Sanctum bearer tokens.

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

## 3. Event Discovery

### Public endpoints

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/events` | Event index with filters, includes, and sorting |
| `GET` | `/events/{eventOrSlug}` | Event detail by UUID or slug |

### `GET /events`

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
