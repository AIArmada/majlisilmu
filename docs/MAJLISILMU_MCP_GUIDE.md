# MajlisIlmu MCP Guide

Updated: April 20, 2026
Audience: developers and AI-client integrators.

## What MCP Means in MajlisIlmu

MajlisIlmu exposes two web MCP servers plus two local testing handles that AI clients can connect to:

- `Admin MCP` for full admin-surface resource access.
- `Member MCP` for Ahli-scoped access to the member surface.

Both web servers are registered in `routes/ai.php` and protected by bearer-token or OAuth-based authentication. Local testing handles are also registered there so you can exercise the full MCP stack through the app container and its configured database.

## How To Read Connector Reports

When you are comparing a report, a Filament page, an HTTP admin endpoint, and an MCP response, treat them as different surfaces with different contracts:

| Surface | Source of truth | Use it for | Do not assume |
|---|---|---|---|
| Filament admin panel | `app/Filament/Resources/*` and related pages | Human admin UX and resource configuration | Exact API payload shape |
| Raw HTTP admin API | `docs/MAJLISILMU_MOBILE_API_REFERENCE.md` and `/api/v1/admin` | HTTP admin contracts, schemas, and record payloads | MCP write-schema parity |
| MCP admin server | `/mcp/admin` | AI-client tool calls and sanitized write schemas | Raw HTTP schema parity |

Key rules:

- `GET /api/v1/admin/{resourceKey}/schema` documents the raw HTTP admin contract.
- `GET /mcp/admin` uses a separate MCP write-schema formatter; destructive `clear_*` media flags are removed there, while supported media/file fields are advertised with JSON base64 descriptor metadata.
- `current_media` contains metadata only; it does not expose signed or temporary URLs.
- Generic user record payloads intentionally redact `email`, `email_verified_at`, `phone`, `phone_verified_at`, `daily_prayer_institution_id`, and `friday_prayer_institution_id`.
- Record lookups use `route_key` for record-specific paths, and missing records return 404 rather than a generic server error.
- Supported admin update flows are schema-guided `PUT` requests: required fields must be sent, while optional omitted fields are merged by the underlying save actions when that resource supports merge-based updates.

If you are writing or reviewing an AI-generated report, read the raw HTTP admin reference for `/api/v1/admin` behavior and this guide for MCP-only behavior. Do not infer one surface from the other.

## Available Servers

### Admin MCP

- Route: `/mcp/admin`
- Server class: `App\Mcp\Servers\AdminServer`
- Intended for admin workflows such as resource discovery, record browsing, record detail, named relation traversal, schema discovery, and supported create/update operations.

### Member MCP

- Route: `/mcp/member`
- Server class: `App\Mcp\Servers\MemberServer`
- Intended for Ahli/member workflows such as resource discovery, record browsing, record detail, schema discovery, and supported updates on writable member-visible resources.

## How To Connect

### 1. Issue a bearer token

The quickest way to use MCP with this app is to issue a scoped Sanctum token for the server you want to call.

```shell
php artisan mcp:token someone@example.com "VS Code Admin MCP" --server=admin
php artisan mcp:token someone@example.com "VS Code Member MCP" --server=member
```

Use the returned token as an `Authorization: Bearer <token>` header in your MCP client.

The token is server-scoped:

- `admin` tokens carry the `mcp:admin` ability.
- `member` tokens carry the `mcp:member` ability.

### 2. Or use OAuth-capable clients

The app also registers MCP OAuth routes under `oauth/mcp` in `routes/ai.php`.

Use this path when your client supports OAuth instead of manual bearer tokens. The OAuth setup is backed by Passport and the published MCP authorization view in `resources/views/mcp/authorize.blade.php`.

### ChatGPT Connector Settings

If ChatGPT shows the optional OAuth advanced settings, use these values for this server:

- Registration method: `Dynamic Client Registration (DCR)`
- Default scopes: `mcp:use`
- Base scopes: leave blank unless the server later advertises additional scopes
- OpenID support: leave disabled, because this server does not advertise an OIDC configuration URL
- Auth URL / Token URL / Registration URL / Authorization server base / Resource: keep the discovered endpoints that ChatGPT already populated

Why this is the right choice:

- The MCP server advertises a Registration URL, so DCR is available.
- CIMD is unavailable because the server does not advertise CIMD support.
- The server’s MCP OAuth flow is built around the `mcp:use` scope.
- The server does not advertise OIDC discovery, so OpenID Connect is not needed for this connector.

If ChatGPT asks which resource to use, point it at the same MCP resource route you are connecting to, such as `/mcp/admin` for the admin server or `/mcp/member` for the member server.

### 3. Or manage tokens inside the app

Authenticated users can manage their MCP tokens from the account-settings API:

- `GET /api/v1/account-settings/mcp-tokens`
- `POST /api/v1/account-settings/mcp-tokens`
- `DELETE /api/v1/account-settings/mcp-tokens/{tokenId}`

### 4. Test the local MCP stack

If you want to verify the MCP servers against your local database without going through remote MCP transport, use the local handles:

```shell
php artisan mcp:inspector majlisilmu-admin-local
php artisan mcp:inspector majlisilmu-member-local
```

These local handles run inside the app process, so they use the database configured for the current environment:

- In local development, they point at your local database connection.
- In automated tests, they use the test database created for the test run.

Use these handles when you want to confirm the MCP tools, resource discovery, and auth behavior locally before wiring the same server class into a remote client.

## Inspect And Debug

Use the MCP Inspector when you want to test the server before wiring it into a client:

```shell
php artisan mcp:inspector /mcp/admin
php artisan mcp:inspector /mcp/member
php artisan mcp:inspector majlisilmu-admin-local
php artisan mcp:inspector majlisilmu-member-local
```

The inspector is useful for:

- Verifying authentication.
- Checking which tools are exposed.
- Trying tool calls interactively.
- Debugging client configuration issues.

## What Each Server Exposes

### How ChatGPT Reads These Tools

ChatGPT does not infer server capabilities from the Laravel controllers or database schema. It reasons from the MCP tool descriptors you expose.

For each tool, ChatGPT sees:

- the tool `name`
- the human-readable `title` / `description`
- the input schema for arguments
- impact annotations such as read-only, destructive, idempotent, and open-world hints
- any UI linkage metadata like `_meta.ui.resourceUri`
- the tool response `structuredContent` and `content`

ChatGPT does **not** read `_meta` as model context unless the tool response explicitly includes it in model-visible content. Treat `_meta` as host/UI-only data.

If you want ChatGPT to understand a capability, expose it as a dedicated tool with a clear schema and description. One tool should map to one user intent, the same way one API operation maps to one HTTP action.

### Admin MCP tool catalog

The admin server is the model-visible API-like surface for admin workflows. The tools below are the current contract that ChatGPT can call:

| Tool | Purpose | Raw HTTP admin equivalent |
|---|---|---|
| `admin-list-resources` | List accessible admin resources and their capability summary | `GET /api/v1/admin/manifest` |
| `admin-get-resource-meta` | Read one admin resource's metadata, pages, relations, abilities, and write-support flags | `GET /api/v1/admin/{resourceKey}/meta` |
| `admin-list-records` | List records for one admin resource with optional search and pagination | `GET /api/v1/admin/{resourceKey}` |
| `admin-list-related-records` | Traverse a named relation on one admin record | `GET /api/v1/admin/{resourceKey}/{recordKey}/relations/{relation}` |
| `admin-get-record` | Read one admin record and its permissions | `GET /api/v1/admin/{resourceKey}/{recordKey}` |
| `admin-get-write-schema` | Discover the create/update contract for a writable admin record | `GET /api/v1/admin/{resourceKey}/schema` |
| `admin-create-record` | Create or preview a writable admin record | `POST /api/v1/admin/{resourceKey}` |
| `admin-update-record` | Update or preview a writable admin record | `PUT /api/v1/admin/{resourceKey}/{recordKey}` |

Admin tool behavior notes:

- `validate_only=true` is supported for create/update preview flows.
- `current_media` is metadata only; it is useful for form prefill but does not expose signed URLs.
- Media/file upload fields accept JSON base64 descriptors only when the matching write schema advertises them.
- `clear_*` media flags are intentionally rejected in MCP even when the raw HTTP admin schema may mention destructive media handling.
- Read-only tools should be annotated as such so ChatGPT can safely choose them.
- Write tools should be described as schema-guided and idempotent where the server logic supports that behavior.

### Member MCP tool catalog

The member server is the model-visible API-like surface for Ahli-scoped workflows. These tools expose the member boundary as ChatGPT-readable operations:

| Tool | Purpose |
|---|---|
| `member-list-resources` | List accessible Ahli-scoped member resources |
| `member-get-resource-meta` | Read one member resource's metadata, permissions, and available write support |
| `member-list-records` | List records for one member resource with optional search and pagination |
| `member-get-record` | Read one member record by resource key and record key |
| `member-get-write-schema` | Discover the writable update schema for one member record |
| `member-update-record` | Update a writable member record using the schema-guided payload contract |

Member tool behavior notes:

- Member tools are constrained to the Ahli workspace boundary and live membership relationships.
- Update tools are schema-guided and should be treated as the member-side API equivalent of the relevant HTTP workflow.
- Media/file upload fields accept JSON base64 descriptors only when the matching member write schema advertises them.
- As with admin tools, ChatGPT only understands what the tool descriptor exposes; if a capability is not registered as a tool, the model will not assume it exists.

### MCP media/file upload contract

MCP write tools are JSON tools, not multipart HTTP endpoints. When a schema advertises a media/file field, send a descriptor object instead of a browser file upload:

```json
{
  "filename": "poster.png",
  "mime_type": "image/png",
  "content_base64": "iVBORw0KGgo..."
}
```

Accepted aliases are `file_name` or `name` for `filename`, `mime` for `mime_type`, and `base64` or `data` for `content_base64`. Data URLs are accepted for `content_base64`. Filename extensions are recommended; when they are omitted, the server derives the staged extension from `mime_type`.

Schema fields describe the exact upload rules:

- `mcp_upload.shape` is `file_descriptor` for a single file and `array<file_descriptor>` for multiple files.
- `accepted_mime_types`, `max_file_size_kb`, and `max_files` are authoritative for that field.
- `mcp_upload.replacement_semantics` describes whether the submitted descriptor or descriptor array replaces the target media collection.
- `validate_only=true` previews normalize descriptors into file summaries without persisting media.
- `current_media` stays metadata-only and does not expose signed or temporary URLs.
- `clear_*` media flags remain unsupported through MCP and are rejected even when clients submit them manually.

### Admin MCP resource scope

The admin tool catalog above is the canonical list of model-visible operations. The admin server exposes those tools for resource discovery, record browsing, relation traversal, schema discovery, and supported create/update workflows.

Current supported admin resources include:

- `speakers`
- `events`
- `institutions`
- `references`
- `subdistricts`

Write-tool preview tip:

- `admin-create-record` and `admin-update-record` accept `validate_only=true` to validate, normalize, and preview a write without persisting it.
- Preview responses return the normalized payload plus warning metadata for supported write-side checks.
- MCP write schemas advertise supported media/file fields with JSON descriptor metadata, do not advertise destructive media clear-flags, and reject clear-flags if a client submits them anyway.
- Update previews also include the current record snapshot so you can compare what would change before retrying without `validate_only`.

### Member MCP resource scope

The member tool catalog above is the canonical list of model-visible operations. The member server exposes those tools for resource discovery, record browsing, schema discovery, and supported member-side update workflows.

Current supported member resources include:

- `institutions`
- `speakers`
- `references`
- `events`

## Configuration Notes

The MCP configuration lives in `config/mcp.php`.

Useful environment variables:

- `MCP_REDIRECT_DOMAINS` — add hosted OAuth redirect domains here.
- `MCP_CUSTOM_SCHEMES` — allow private-use callback schemes for desktop clients such as `vscode` or `claude`.
- `MCP_AUTHORIZATION_SERVER` — override the OAuth issuer identifier if needed.
- `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` — optional raw PEM secrets for Passport; leave them blank to use the generated `storage/oauth-*.key` files. Do not set file paths here.

## Common Troubleshooting Checks

- `401 Unauthorized` usually means the client is missing a bearer token or the token does not match the target server.
- `403 Forbidden` usually means the user does not have the required admin or member access.
- OAuth redirect failures usually mean the redirect domain or custom scheme has not been allowlisted in `config/mcp.php`.

## Relevant Files

- `routes/ai.php` — MCP server registration and OAuth routes.
- `app/Mcp/Servers/AdminServer.php` — admin MCP server definition.
- `app/Mcp/Servers/MemberServer.php` — member MCP server definition.
- `app/Support/Mcp/McpWriteSchemaFormatter.php` — sanitized MCP write-schema formatter.
- `app/Support/Mcp/McpFilePayloadNormalizer.php` — JSON file descriptor staging and validation for MCP writes.
- `routes/ai.php` — web and local MCP registration.
- `app/Console/Commands/IssueMcpToken.php` — command for issuing scoped tokens.
- `app/Support/Mcp/McpTokenManager.php` — token issuance, listing, and revocation logic.
- `tests/Feature/Mcp/AdminServerTest.php` — admin MCP regression coverage.
- `tests/Feature/Mcp/MemberServerTest.php` — member MCP regression coverage.
- `tests/Feature/Mcp/LocalServerRegistrationTest.php` — local MCP registration coverage.

## Quick Start

1. Decide whether you need the admin or member server.
2. Issue a matching bearer token, or start an OAuth flow if your client supports it.
3. Point your MCP client at `/mcp/admin` or `/mcp/member`.
4. Use `php artisan mcp:inspector` if you need to verify the connection or inspect available tools; prefer the local handles when you want to exercise the app’s local database and full MCP transport end to end.

## Appendix: Compact Tool Reference

Use this as the quick scan list when you want ChatGPT to reason about the connector as an API surface.

### Admin MCP tools

| Tool | When to use it | Core arguments |
|---|---|---|
| `admin-list-resources` | Discover accessible admin resources | `verbose?`, `writable_only?` |
| `admin-get-resource-meta` | Inspect one resource’s metadata, routes, relations, and abilities | `resource_key` |
| `admin-list-records` | Search and paginate records for one admin resource | `resource_key`, `search?`, `page?`, `per_page?` |
| `admin-list-related-records` | Traverse a named relation on a record | `resource_key`, `record_key`, `relation`, `page?`, `per_page?` |
| `admin-get-record` | Read one admin record and its permissions | `resource_key`, `record_key` |
| `admin-get-write-schema` | Fetch the create/update contract for a writable admin record | `resource_key`, `operation`, `record_key?` |
| `admin-create-record` | Create or preview a writable admin record | `resource_key`, `payload`, `validate_only?` |
| `admin-update-record` | Update or preview a writable admin record | `resource_key`, `record_key`, `payload`, `validate_only?` |

### Member MCP tools

| Tool | When to use it | Core arguments |
|---|---|---|
| `member-list-resources` | Discover accessible Ahli-scoped resources | `verbose?` |
| `member-get-resource-meta` | Inspect one member resource’s metadata and write support | `resource_key` |
| `member-list-records` | Search and paginate records for one member resource | `resource_key`, `search?`, `page?`, `per_page?` |
| `member-get-record` | Read one member record | `resource_key`, `record_key` |
| `member-get-write-schema` | Fetch the writable update contract for one member record | `resource_key`, `record_key` |
| `member-update-record` | Update a writable member record | `resource_key`, `record_key`, `payload`, `validate_only?` |

### Reading rules for the appendix

- Read-only tools should be treated as discovery and preview operations.
- Write tools are schema-guided and should always be preceded by the matching write-schema call.
- The model should treat these tools as the full ChatGPT-visible connector API. If a capability is not listed here, it is not part of the supported tool contract.
- For tiny call examples, see `docs/MAJLISILMU_MCP_TOOL_EXAMPLES.md`.
