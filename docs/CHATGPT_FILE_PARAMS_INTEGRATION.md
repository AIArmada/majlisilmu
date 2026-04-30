# ChatGPT File Params Integration for MCP

## Overview

This document describes how MajlisIlmu MCP tools now support **ChatGPT file parameters** (`download_url`, `file_id`) alongside existing `content_base64` and `content_url` descriptors.

## What Changed

### Core Changes

#### 1. **McpFilePayloadNormalizer** (`app/Support/Mcp/McpFilePayloadNormalizer.php`)
- **Now accepts `download_url`** as a synonym for `content_url`
- **Ignores `file_id`** metadata (informational only from ChatGPT)
- **Improved error messages** to mention ChatGPT file param support
- **Supports both naming conventions**:
  - `content_url`, `contentUrl` (existing)
  - `download_url`, `downloadUrl` (ChatGPT)
  - `file_id`, `fileId` (metadata; ignored)

#### 2. **McpWriteSchemaFormatter** (`app/Support/Mcp/McpWriteSchemaFormatter.php`)
- **Updated `media_upload_transport`** from `'json_base64_descriptor'` to `'json_base64_descriptor_or_download_url'`
- **Enhanced `file_descriptor_shape`** documentation to list all supported fields:
  - `filename` (required)
  - `mime_type` (optional)
  - `content_base64` or `content_url` or `download_url` (required, one of)
  - `file_id` (ChatGPT metadata; optional, ignored)

#### 3. **MCP Tool Metadata**
- **MemberSubmitMembershipClaimTool** now declares `_meta['openai/fileParams'] = ['evidence']`
  - ChatGPT connectors use this to know which fields accept file params
- **AdminCreateRecordTool** and **AdminUpdateRecordTool** add metadata notes about media fields in payload object
  - Media-capable resources: Event, Institution, Reference, Report, Speaker, Venue, Series, DonationChannel, Inspiration, Space

## File Parameter Shape

### Accepted Formats

#### ChatGPT Widget Upload (Recommended)
```json
{
  "evidence": [
    {
      "filename": "proof.png",
      "download_url": "https://api.openai.com/files/file_id/content",
      "file_id": "file_12345",
      "mime_type": "image/png"
    }
  ]
}
```

#### Traditional Base64 (Fallback)
```json
{
  "evidence": [
    {
      "filename": "proof.png",
      "content_base64": "iVBORw0KGgoAAAANSUhEUgAAAAUA...",
      "mime_type": "image/png"
    }
  ]
}
```

#### Traditional Content URL (Fallback)
```json
{
  "evidence": [
    {
      "filename": "proof.png",
      "content_url": "https://example.com/uploads/proof.png",
      "mime_type": "image/png"
    }
  ]
}
```

### Field Semantics

| Field | Required | Type | Notes |
|-------|----------|------|-------|
| `filename` | Yes | String | Client filename with extension; used for staged file naming |
| `content_base64` | No* | String or Data URL | Raw base64 bytes; supports `data:mime;base64,...` |
| `content_url` | No* | URL String | HTTP(S) URL; server fetches with no-redirect, no multipart |
| `download_url` | No* | URL String | ChatGPT file param; equivalent to `content_url` |
| `file_id` | No | String | ChatGPT file ID; metadata only, ignored by server |
| `mime_type` | No | String | IANA media type (e.g., `image/jpeg`); inferred from Content-Type if omitted |

**One of `content_base64`, `content_url`, or `download_url` is required per file descriptor.*

## Migration Path

### For ChatGPT Connector Developers

1. **Detect media fields** in the tool schema via `media_uploads_supported` and `file_descriptor_shape`
2. **User uploads files** via widget (ChatGPT handles `uploadFile` / `selectFiles`)
3. **Pass file params** with `download_url` and `file_id`:
   ```javascript
   {
     evidence: [{
       filename: "user-evidence.pdf",
       download_url: await window.openai.getFileDownloadUrl(fileId),
       file_id: fileId,
       mime_type: "application/pdf"
     }]
   }
   ```
4. **Server normalizes** to `UploadedFile` internally; validation continues as normal

### For Non-ChatGPT MCP Clients

- Continue using `content_base64` or `content_url`
- No changes required; full backward compatibility

## Tools Supporting ChatGPT File Params

### Member-Facing (Per-User Evidence)
- **member-submit-membership-claim**: `evidence` field (array of files, max 8)

### Admin-Facing (Dynamic Resource Media)
- **admin-create-record** (payload object fields):
  - `poster`, `gallery` (Event)
  - `logo`, `cover`, `gallery` (Institution)
  - `front_cover`, `back_cover`, `gallery` (Reference)
  - `evidence` (Report)
  - `avatar`, `main`, `gallery` (Speaker)
  - `main`, `gallery` (Venue)
  - `cover`, `gallery` (Series)
  - `qr` (DonationChannel)
  - `main` (Inspiration, Space)

- **admin-update-record** (same fields as create)

## Testing

### Unit Tests
- `tests/Unit/McpFilePayloadNormalizerTest.php`:
  - ✅ Accepts camelCase keys
  - ✅ Accepts `content_url` with charset params
  - ✅ **NEW**: Accepts ChatGPT `download_url`
  - ✅ **NEW**: Ignores `file_id` metadata

### Integration Tests
- `tests/Feature/Mcp/MemberServerTest.php`:
  - ✅ Member write schema includes file descriptor shape
  - ✅ Media upload transport updated to `json_base64_descriptor_or_download_url`

- `tests/Feature/Mcp/AdminServerTest.php`:
  - ✅ Admin write schema includes file descriptor shape
  - ✅ Media upload transport updated

## Error Handling

When files cannot be processed, the normalizer provides context-aware errors:

```
The MCP file descriptor must include either content_base64, content_url, or download_url. 
MCP tools do not accept multipart/form-data payloads. 
ChatGPT connectors may pass {download_url, file_id}.
```

For failed downloads:
```
The MCP file descriptor (file_id: file_12345) could not be decoded.
```

## Security Notes

- **SSRF Protection**: `content_url` and `download_url` are validated as safe HTTP(S) URLs
  - No redirects allowed
  - Localhost/private IPs rejected (see `assertSafeContentUrl()`)
  - 5s connect timeout, 20s read timeout

- **File Validation**: Same as non-ChatGPT paths
  - MIME type whitelist per field
  - Max file size enforced
  - Max files per collection enforced

- **Metadata**: `file_id` is never persisted; only used during normalization

## References

- **OpenAI Apps SDK**: https://developers.openai.com/apps-sdk/build/mcp-server
- **OpenAI File Params**: https://developers.openai.com/api/docs/mcp#file-params-meta-extension
- **MCP Protocol**: https://modelcontextprotocol.io/

## Troubleshooting

### ChatGPT Connector Not Recognizing File Fields

**Check**:
1. Tool declares `_meta['openai/fileParams']` for array fields or payload subfields
2. Schema includes `file_descriptor_shape` documentation
3. Field is declared with type `file` or `array<file>`

**Example**:
```php
$tool['_meta'] = ['openai/fileParams' => ['evidence']];
```

### Download URL Fails

**Check**:
1. URL is absolute HTTP(S)
2. No redirects are used (e.g., short URLs must resolve to final URL)
3. Server is accessible from Laravel app environment
4. File is non-empty (>0 bytes)
5. Content-Type header is present and valid

### MIME Type Mismatch

**Check**:
1. `mime_type` in descriptor matches allowed types for field
2. `Content-Type` response header from `download_url` is valid
3. File extension in `filename` can infer correct type if MIME omitted

---

**Last Updated**: April 2026  
**Status**: Implemented & Tested ✅
