# Filament Form Data Handling with Enums

## Critical: Enum Serialization/Deserialization in Filament Forms

### Context
When working with PHP Backed Enums in Filament forms, understanding how Filament handles enum serialization and deserialization is crucial for writing correct conditional logic.

### The Behavior

**Inside Form Field Closures** (e.g., `->disabled()`, `->visible()`, `->required()`, etc.):
- When you use `$get('field_name')` to retrieve form data, Filament automatically **deserializes string values back into enum instances**.
- Arrays will contain **enum objects**, not strings.
- **Use enum instances directly for comparison**: `EventAgeGroup::Children` (NOT `->value`)

**Example:**
```php
// ✅ CORRECT - Use enum instances directly
->disabled(function (Get $get): bool {
    $ageGroups = $get('age_group') ?? [];
    // $ageGroups contains: [EventAgeGroup::AllAges, EventAgeGroup::Adults]
    return in_array(EventAgeGroup::Children, $ageGroups, true) || 
           in_array(EventAgeGroup::AllAges, $ageGroups, true);
})

// ❌ WRONG - Using ->value will NOT match
->disabled(function (Get $get): bool {
    $ageGroups = $get('age_group') ?? [];
    // This will always return false because 'children' string !== EventAgeGroup::Children enum object
    return in_array(EventAgeGroup::Children->value, $ageGroups, true);
})
```

**In Submit/Action Methods** (e.g., `submit()`, `action()`, after validation):
- Form data is **serialized** and contains **string values**.
- Arrays will contain **strings**, not enum objects.
- **Use `->value` property for comparison**: `EventAgeGroup::Children->value`

**Example:**
```php
public function submit(): void
{
    $validated = $this->form->getState();
    $ageGroups = $validated['age_group'] ?? [];
    
    // $ageGroups contains: ['all_ages', 'adults'] (strings)
    
    // ✅ CORRECT - Use ->value for string comparison
    if (in_array(EventAgeGroup::Children->value, $ageGroups, true) || 
        in_array(EventAgeGroup::AllAges->value, $ageGroups, true)) {
        $validated['children_allowed'] = true;
    }
}
```

### Debugging Tip
If you're unsure what format the data is in, add logging:

```php
\Log::info('Form data debug', [
    'data' => $get('field_name'),
    'types' => array_map('gettype', (array)$get('field_name')),
]);
```

Then check `storage/logs/laravel.log` to see if you're dealing with enum objects or strings.

### Summary Table

| Context | Data Format | Comparison Method | Example |
|---------|-------------|-------------------|---------|
| Form field closures (`->disabled()`, `->visible()`, etc.) | Enum objects | Use enum directly | `in_array(EventAgeGroup::Children, $data, true)` |
| Submit/action methods, validated data | Strings | Use `->value` | `in_array(EventAgeGroup::Children->value, $data, true)` |
| Database queries | Strings (stored as backing values) | Use `->value` | `where('age_group', EventAgeGroup::Children->value)` |

### When This Matters
- Conditional form logic: `->disabled()`, `->visible()`, `->required()`, `->hidden()`
- Field dependencies: `->afterStateUpdated()`, `->reactive()`
- Any closure receiving `Get $get` parameter
- Validation rules that check other fields

### Key Takeaway
**Filament automatically converts between enum objects (for PHP logic) and strings (for storage/transport).** Always check your context to know which format you're working with.

---

# Model Sorting with Spatie Eloquent Sortable

## Overview
This application uses `spatie/eloquent-sortable` for consistent model ordering. Always use this package instead of manually managing sort columns.

## Implementation Pattern

### Model Setup
```php
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class MyModel extends Model implements Sortable
{
    use SortableTrait;

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected $fillable = [
        'name',
        'order_column', // Always include in fillable
    ];
}
```

### Migration
```php
Schema::create('my_models', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->unsignedInteger('order_column')->nullable(); // Always nullable
    $table->timestamps();
});
```

### Querying Sorted Records
```php
// Use the ->ordered() scope provided by the trait
$records = MyModel::ordered()->get();

// In relationships
public function items(): HasMany
{
    return $this->hasMany(Item::class)->ordered();
}
```

### Key Rules
1. **Column name**: Always use `order_column` for consistency across models
2. **Nullable**: The column should be nullable (SortableTrait handles auto-assignment)
3. **No manual sorting**: Don't manually set `order_column` values; let the trait manage it
4. **Use `->ordered()` scope**: Always use the provided scope instead of `->orderBy('order_column')`

### Models Using Sortable
- `Tag` (inherited from Spatie Tags, scoped by type)
- `Topic`
- `EventType`

---

# Unified Tag System Architecture

## Overview
This application uses **Spatie Tags** with a **TagType enum** for organizing tags by category. All tag functionality uses Spatie's native polymorphic `taggables` table.

## TagType Enum
Located at `App\Enums\TagType`, provides metadata for each tag type:

```php
TagType::Domain->label();       // "Domain"
TagType::Domain->color();       // "primary"
TagType::Domain->icon();        // "heroicon-o-academic-cap"
TagType::Domain->description(); // "Core Islamic knowledge areas..."
TagType::Domain->order();       // 10
```

## Type Storage & Access

The `type` column stores string values ('domain', 'discipline', 'source', 'issue') to maintain compatibility with Spatie's native methods:

```php
$tag->type;        // Returns: 'domain' (string)
$tag->type_enum;   // Returns: TagType::Domain (enum instance)
```

**Why not cast to enum?** Spatie's `tagsWithType()` method does strict string comparison, so the type must remain a string in the model. Use the `type_enum` accessor when you need enum functionality.

## Tag Types
| Type | Value | Purpose |
|------|-------|---------|
| Domain | `domain` | Core Islamic knowledge areas (Aqidah, Syariah, Akhlak) |
| Discipline | `discipline` | Specific fields of study (Tafsir, Sirah, Fiqh, etc.) |
| Source | `source` | Reference sources (Quran, Hadith, Turath, etc.) |
| Issue | `issue` | Contemporary themes/topics (Rasuah, Kepimpinan, etc.) |

## Usage

### Tagging Events
```php
// Attach tags to an event
$event->attachTag($tag);
$event->attachTags([$tag1, $tag2]);

// Sync tags (replaces all existing tags)
$event->syncTags([$tag1, $tag2]);

// Detach tags
$event->detachTag($tag);
$event->detachTags();
```

### Querying Tags
```php
// Get all tags of a specific type (verified + pending)
$domainTags = Tag::ofType(TagType::Domain)->whereIn('status', ['verified', 'pending'])->get();
$issueTags = Tag::ofType('issue')->whereIn('status', ['verified', 'pending'])->get();

// Spatie's native method (use with status filter)
$domainTags = Tag::getWithType('domain')->filter(fn($tag) => in_array($tag->status, ['verified', 'pending']));

// Get event's tags of specific type
$domainTags = $event->tagsWithType('domain');

// Get all tags ordered
$tags = Tag::ordered()->get();
```

### Tag Status & Moderation
- Tags have a `status` column with values: `'pending'`, `'verified'`
- Pre-seeded tags are `'verified'` (Domain, Source types are pre-seeded only)
- User-created tags (Discipline, Issue) are created as `'pending'`
- When an event is approved, all attached pending tags are auto-verified
- Show both `'verified'` and `'pending'` tags in form dropdowns (similar to Speaker/Institution/Venue)

### Tag Sorting
- Tags use Spatie Eloquent Sortable with `order_column`
- Sorting is scoped by `type` (tags within same type are ordered independently)
- Auto-assigns order when created

## Key Principles
1. **Use native Spatie methods**: `attachTag()`, `syncTags()`, `tagsWithType()`, etc.
2. **No custom pivot**: Everything uses `taggables` table (polymorphic)
3. **Type-based organization**: Use `TagType` enum for categorization and metadata
4. **Status-based moderation**: User-created tags start as `'pending'`, auto-verify on event approval
5. **Keep it simple**: No extra fields like `is_active`, `is_system`, `description`, `weight`, or `is_primary`

---

# Testing Best Practices

## Running Tests

Always use **parallel execution** for faster test runs:

```bash
# Run all tests in parallel
vendor/bin/pest --parallel

# Run specific tests in parallel
vendor/bin/pest --parallel --filter=SubmitEvent

# Run tests with compact output in parallel
vendor/bin/pest --parallel --compact
```

### Why Parallel?
- **Speed**: Tests run significantly faster by utilizing multiple CPU cores
- **Efficiency**: Reduces CI/CD pipeline time
- **Best practice**: Pest's parallel mode handles database isolation automatically

### Alternative Commands
While `php artisan test` can be used, prefer `vendor/bin/pest --parallel` for optimal performance:

```bash
# ❌ Slower (sequential)
php artisan test --filter=SubmitEvent

# ✅ Faster (parallel)
vendor/bin/pest --parallel --filter=SubmitEvent
```

### Key Points
- Parallel execution is safe for all tests (Pest handles isolation)
- No need to modify existing tests to support parallel mode
- Default behavior - no additional configuration required

---

# Static Analysis Safety for Runtime Extensions

When a method looks "undefined" in static analysis, do not remove it until you verify its source.

## Required Verification Before Removal
1. Search for runtime extensions first:
   - `macro()` / `hasMacro()` in service providers
   - package mixins/traits
   - plugin-specific extensions (for example Filament add-ons like quick-add select)
2. Confirm if the method is intentionally runtime-provided (for example `Select::macro(...)`).
3. If runtime-provided, preserve behavior and fix static analysis with a narrow rule (stub or focused ignore pattern), instead of deleting the method call.
4. Only remove a method when you have confirmed there is no implementation source and no feature dependency.

## Practical Rule
- Behavior safety takes priority over static-analysis convenience.
- Never remove feature methods such as `->closeOnSelect()` or `->quickAdd()` without source verification and impact check.
