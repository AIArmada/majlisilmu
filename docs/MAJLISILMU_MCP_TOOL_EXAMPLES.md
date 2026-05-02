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

### Generate an event cover image

Event image generation uses a 3-step workflow. First, call the prompt to get the engineered prompt and reference images:

```json
{
  "prompt": "admin-event-cover-image-prompt",
  "arguments": {
    "event_key": "weekly-kuliah-selasa",
    "creative_direction": "Premium editorial cover with deep emerald, warm gold, and strong Malay typography.",
    "include_existing_media": true,
    "max_reference_media": 6
  }
}
```

Then generate the image using ChatGPT native image generation with the returned prompt and reference images. Finally, upload the result:

```json
{
  "tool": "admin-upload-event-cover-image",
  "arguments": {
    "event_key": "weekly-kuliah-selasa",
    "image": {
      "filename": "event-cover.png",
      "download_url": "https://api.openai.com/files/file_xxx/content",
      "file_id": "file_xxx"
    },
    "creative_direction": "Premium editorial cover with deep emerald, warm gold, and strong Malay typography."
  }
}
```

The upload tool saves the image as a 16:9 image in the Event `cover` media collection. Use `admin-event-poster-image-prompt` and `admin-upload-event-poster-image` when the intended asset is the 4:5 external-distribution poster.

When reference media is enabled, speaker context selection follows this fallback order: speaker `cover`, then speaker `avatar`, then organizer institution media from `event->organizer`.

If the prompt call fails while attaching reference media, retry the prompt without reference attachments:

```json
{
  "prompt": "admin-event-cover-image-prompt",
  "arguments": {
    "event_key": "weekly-kuliah-selasa",
    "creative_direction": "Premium editorial cover with deep emerald, warm gold, and strong Malay typography.",
    "include_existing_media": false,
    "max_reference_media": 0
  }
}
```

### Generate an event poster image

```json
{
  "prompt": "admin-event-poster-image-prompt",
  "arguments": {
    "event_key": "weekly-kuliah-selasa",
    "creative_direction": "Information-rich social poster with clear hierarchy and strong readability.",
    "include_existing_media": true,
    "max_reference_media": 6
  }
}
```

Then generate and upload:

```json
{
  "tool": "admin-upload-event-poster-image",
  "arguments": {
    "event_key": "weekly-kuliah-selasa",
    "image": {
      "filename": "event-poster.png",
      "download_url": "https://api.openai.com/files/file_yyy/content",
      "file_id": "file_yyy"
    }
  }
}
```

The upload tool saves the image as a 4:5 portrait image in the Event `poster` media collection.

When reference media is enabled, speaker context selection follows this fallback order: speaker `cover`, then speaker `avatar`, then organizer institution media from `event->organizer`.

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

Fetch the write schema first and follow the field-specific `mcp_upload`, `accepted_mime_types`, `max_file_size_kb`, and `max_files` metadata. Single-file fields use one descriptor object. Multi-file fields use an array of descriptor objects. Descriptors support `content_base64`, `content_url`, or ChatGPT `download_url` / `file_id` parameters.

For event media writes, ratio checks are server-enforced on every write surface: `cover` must be `16:9` and `poster` must be `4:5`.

**Example with base64:**

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
      }
    },
    "validate_only": true
  }
}
```

**Manual event cover replacement with base64 descriptor (fallback path):**

```json
{
  "tool": "admin-update-record",
  "arguments": {
    "resource_key": "events",
    "record_key": "weekly-kuliah-selasa",
    "payload": {
      "cover": {
        "filename": "weekly-kuliah-selasa-cover.png",
        "mime_type": "image/png",
        "content_base64": "iVBORw0KGgo..."
      }
    },
    "validate_only": false
  }
}
```

**Example with ChatGPT file params (from file widget):**

```json
{
  "tool": "admin-update-record",
  "arguments": {
    "resource_key": "speakers",
    "record_key": "ahmad-fauzi-my",
    "payload": {
      "gallery": [
        {
          "filename": "lecture-hall.webp",
          "mime_type": "image/webp",
          "download_url": "https://api.openai.com/files/file_id/content",
          "file_id": "file_12345"
        }
      ]
    },
    "validate_only": false
  }
}
```

### Create or update a book part reference

Fetch the live schema first so the client sees the current enum values and allowed parent-book rules.

```json
{ "tool": "admin-get-write-schema", "arguments": { "resource_key": "references", "operation": "update", "record_key": "riyadhus-solihin-jilid-2" } }
```

```json
{
  "tool": "admin-update-record",
  "arguments": {
    "resource_key": "references",
    "record_key": "riyadhus-solihin-jilid-2",
    "payload": {
      "title": "Riyadhus Solihin",
      "type": "book",
      "parent_reference_id": "0195f51f-70d6-70e5-bf9d-bc2e04b64f7a",
      "part_type": "jilid",
      "part_number": "2",
      "part_label": "Jilid 2"
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

### Generate an accessible event cover image

Event image generation uses a 3-step workflow. First, call the prompt to get the engineered prompt and reference images:

```json
{
  "prompt": "member-event-cover-image-prompt",
  "arguments": {
    "event_key": "weekly-kuliah-selasa",
    "creative_direction": null,
    "include_existing_media": true,
    "max_reference_media": 6
  }
}
```

Then generate the image using ChatGPT native image generation with the returned prompt and references. Finally, upload the result:

```json
{
  "tool": "member-upload-event-cover-image",
  "arguments": {
    "event_key": "weekly-kuliah-selasa",
    "image": {
      "filename": "event-cover.png",
      "download_url": "https://api.openai.com/files/file_xxx/content",
      "file_id": "file_xxx"
    }
  }
}
```

The upload tool saves the image as a 16:9 image in the accessible Event `cover` media collection.

When reference media is enabled, speaker context selection follows this fallback order: speaker `cover`, then speaker `avatar`, then organizer institution media from `event->organizer`.

### Generate an accessible event poster image

```json
{
  "prompt": "member-event-poster-image-prompt",
  "arguments": {
    "event_key": "weekly-kuliah-selasa",
    "creative_direction": null,
    "include_existing_media": true,
    "max_reference_media": 6
  }
}
```

Then generate and upload:

```json
{
  "tool": "member-upload-event-poster-image",
  "arguments": {
    "event_key": "weekly-kuliah-selasa",
    "image": {
      "filename": "event-poster.png",
      "download_url": "https://api.openai.com/files/file_yyy/content",
      "file_id": "file_yyy"
    }
  }
}
```

The upload tool saves the image as a 4:5 portrait image in the accessible Event `poster` media collection. Use `member-event-cover-image-prompt` and `member-upload-event-cover-image` when the intended asset is the 16:9 website/app cover.

When reference media is enabled, speaker context selection follows this fallback order: speaker `cover`, then speaker `avatar`, then organizer institution media from `event->organizer`.

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

**Example with base64:**

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

**Example with ChatGPT file params:**

```json
{
  "tool": "member-submit-membership-claim",
  "arguments": {
    "subject_type": "speaker",
    "subject_id": "019d5cb5-7de1-7055-a4d3-b57ab007331e",
    "justification": "I am authorized to represent this speaker.",
    "evidence": [
      {
        "filename": "authorization-letter.pdf",
        "mime_type": "application/pdf",
        "download_url": "https://api.openai.com/files/file_id/content",
        "file_id": "file_xyz"
      }
    ]
  }
}
```

### Update a member-linked reference part label

```json
{ "tool": "member-get-write-schema", "arguments": { "resource_key": "references", "record_key": "019d5cb5-7de1-7055-a4d3-b57ab007331e" } }
```

```json
{
  "tool": "member-update-record",
  "arguments": {
    "resource_key": "references",
    "record_key": "019d5cb5-7de1-7055-a4d3-b57ab007331e",
    "payload": {
      "title": "Riyadhus Solihin",
      "type": "book",
      "parent_reference_id": "0195f51f-70d6-70e5-bf9d-bc2e04b64f7a",
      "part_type": "jilid",
      "part_number": "2",
      "part_label": "Jilid Kedua"
    },
    "validate_only": true
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
- Media/file fields use JSON file descriptors (base64, URL, or ChatGPT file params) only when the write schema advertises them; destructive `clear_*` media flags are rejected.
- If a capability is not listed in the server tool catalog, ChatGPT should not assume it exists.
