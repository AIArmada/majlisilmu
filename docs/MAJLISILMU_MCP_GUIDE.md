# MajlisIlmu MCP Guide

Updated: April 19, 2026
Audience: developers and AI-client integrators.

## What MCP Means in MajlisIlmu

MajlisIlmu exposes two MCP servers that AI clients can connect to:

- `Admin MCP` for full admin-surface resource access.
- `Member MCP` for Ahli-scoped access to the member surface.

Both servers are registered in `routes/ai.php` and protected by bearer-token or OAuth-based authentication.

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

## Inspect And Debug

Use the MCP Inspector when you want to test the server before wiring it into a client:

```shell
php artisan mcp:inspector /mcp/admin
php artisan mcp:inspector /mcp/member
```

The inspector is useful for:

- Verifying authentication.
- Checking which tools are exposed.
- Trying tool calls interactively.
- Debugging client configuration issues.

## What Each Server Exposes

### Admin MCP

The admin server currently exposes tools for:

- Listing available resources.
- Reading resource metadata.
- Listing records.
- Reading a single record.
- Traversing named relations on a specific record.
- Discovering write schemas.
- Creating supported records.
- Updating supported records.

Current supported admin resources include:

- `speakers`
- `events`
- `institutions`
- `references`
- `subdistricts`

### Member MCP

The member server currently exposes tools for:

- Listing available resources.
- Reading resource metadata.
- Listing records.
- Reading a single record.
- Discovering write schemas.
- Updating supported member-visible records.

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
- `app/Console/Commands/IssueMcpToken.php` — command for issuing scoped tokens.
- `app/Support/Mcp/McpTokenManager.php` — token issuance, listing, and revocation logic.
- `tests/Feature/Mcp/AdminServerTest.php` — admin MCP regression coverage.
- `tests/Feature/Mcp/MemberServerTest.php` — member MCP regression coverage.

## Quick Start

1. Decide whether you need the admin or member server.
2. Issue a matching bearer token, or start an OAuth flow if your client supports it.
3. Point your MCP client at `/mcp/admin` or `/mcp/member`.
4. Use `php artisan mcp:inspector` if you need to verify the connection or inspect available tools.
