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
    "validate_only": true
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

## Reading rules

- Read-only tools are for discovery and preview.
- Write tools are schema-guided.
- If a capability is not listed in the server tool catalog, ChatGPT should not assume it exists.
