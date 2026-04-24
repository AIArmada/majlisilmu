# MajlisIlmu MCP Tool Examples

Use this companion reference alongside `docs/MAJLISILMU_MCP_GUIDE.md` when you want ChatGPT to treat the MCP connector like an API catalog.

For the full connector rules, tool catalog, and API mappings, see `docs/MAJLISILMU_MCP_GUIDE.md`.

## Admin MCP

### Discover resources

```json
{ "tool": "admin-list-resources", "arguments": { "verbose": false, "writable_only": false } }
```

### Read one record

```json
{ "tool": "admin-get-record", "arguments": { "resource_key": "speakers", "record_key": "ahmad-fauzi-my" } }
```

### Read one event with public change projections

```json
{ "tool": "admin-get-record", "arguments": { "resource_key": "events", "record_key": "weekly-kuliah-selasa" } }
```

Event record detail for `events` now includes the public change-surface projection fields `active_change_notice`, `change_announcements`, and `replacement_event` inside `data.record.attributes`.

### Filter an event list

Use enum backing values in `filters`, and use date-only `YYYY-MM-DD` values for `starts_after`, `starts_before`, and `starts_on_local_date`.

```json
{
  "tool": "admin-list-records",
  "arguments": {
    "resource_key": "events",
    "filters": {
      "status": "approved",
      "is_active": true,
      "timing_mode": "absolute"
    },
    "page": 1,
    "per_page": 10
  }
}
```

```json
{
  "tool": "admin-list-records",
  "arguments": {
    "resource_key": "events",
    "search": "Dhuha",
    "starts_on_local_date": "2026-04-24",
    "filters": {
      "status": "approved"
    }
  }
}
```

```json
{
  "tool": "admin-list-records",
  "arguments": {
    "resource_key": "events",
    "starts_after": "2026-04-24",
    "starts_before": "2026-04-30"
  }
}
```

### Preview a write

```json
{ "tool": "admin-get-write-schema", "arguments": { "resource_key": "speakers", "operation": "update", "record_key": "ahmad-fauzi-my" } }
```

```json
{
  "tool": "admin-update-record",
  "arguments": {
    "resource_key": "speakers",
    "record_key": "ahmad-fauzi-my",
    "payload": {
      "name": "Ahmad Fauzi bin Abdullah",
      "gender": "male",
      "status": "verified"
    },
    "validate_only": true,
    "apply_defaults": true
  }
}
```

### Write media descriptors

Fetch the write schema first and follow the field-specific `mcp_upload`, `accepted_mime_types`, `max_file_size_kb`, and `max_files` metadata. Single-file fields use one descriptor object. Multi-file fields use an array of descriptor objects.

```json
{
  "tool": "admin-update-record",
  "arguments": {
    "resource_key": "speakers",
    "record_key": "ahmad-fauzi-my",
    "payload": {
      "name": "Ahmad Fauzi bin Abdullah",
      "gender": "male",
      "status": "verified",
      "avatar": {
        "filename": "avatar.png",
        "mime_type": "image/png",
        "content_base64": "iVBORw0KGgo..."
      },
      "gallery": [
        {
          "filename": "lecture-hall.webp",
          "mime_type": "image/webp",
          "content_base64": "UklGRiQAAABXRUJQVlA4..."
        }
      ]
    },
    "validate_only": true
  }
}
```

### Create a GitHub issue and auto-assign Copilot

```json
{
  "tool": "admin-create-github-issue",
  "arguments": {
    "category": "docs_mismatch",
    "title": "Clarify MCP GitHub issue reporting",
    "summary": "The tool and API contracts should document the same required fields and response shape.",
    "platform": "chatgpt",
    "client_name": "ChatGPT",
    "client_version": "GPT-5.4",
    "tool_name": "admin-create-github-issue",
    "proposal": "Keep one shared API + MCP issue-reporting contract in the docs."
  }
}
```

## Member MCP

### Discover accessible resources

```json
{ "tool": "member-list-resources", "arguments": { "verbose": false } }
```

### Read one record

```json
{ "tool": "member-get-record", "arguments": { "resource_key": "institutions", "record_key": "019d5cb5-7de1-7055-a4d3-b57ab007331e" } }
```

For member-scoped event reads, the same `data.record.attributes.active_change_notice`, `change_announcements`, and `replacement_event` fields are available on `member-get-record` when `resource_key` is `events`.

### Preview a write

```json
{ "tool": "member-get-write-schema", "arguments": { "resource_key": "institutions", "record_key": "019d5cb5-7de1-7055-a4d3-b57ab007331e" } }
```

```json
{
  "tool": "member-update-record",
  "arguments": {
    "resource_key": "institutions",
    "record_key": "019d5cb5-7de1-7055-a4d3-b57ab007331e",
    "payload": {
      "name": "Updated Institution Name"
    },
    "validate_only": false
  }
}
```

### Update member media descriptors

```json
{
  "tool": "member-update-record",
  "arguments": {
    "resource_key": "institutions",
    "record_key": "019d5cb5-7de1-7055-a4d3-b57ab007331e",
    "payload": {
      "name": "Updated Institution Name",
      "cover": {
        "filename": "institution-cover.jpg",
        "mime_type": "image/jpeg",
        "content_base64": "/9j/4AAQSkZJRgABAQ..."
      }
    },
    "validate_only": false
  }
}
```

### Create a plain GitHub issue

```json
{
  "tool": "member-create-github-issue",
  "arguments": {
    "category": "bug",
    "title": "Member MCP issue reporting edge case",
    "summary": "The member tool should create a plain issue without Copilot assignment.",
    "platform": "chatgpt",
    "client_name": "ChatGPT",
    "client_version": "GPT-5.4",
    "tool_name": "member-create-github-issue",
    "expected_behavior": "The issue is created with user and platform context.",
    "actual_behavior": "The feature is not available yet."
  }
}
```

## Reading rules

- Read-only tools are for discovery and preview.
- Write tools are schema-guided.
- Generic MCP delete tools are intentionally not exposed on admin/member servers.
- Media/file fields use JSON base64 descriptors only when the write schema advertises them; destructive `clear_*` media flags are rejected.
- If a capability is not listed in the server tool catalog, ChatGPT should not assume it exists.
