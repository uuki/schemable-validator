# SV::array() - Array Type

The type for multi-select and repeated input fields. Validates each element against the specified schema.

```php
SV::array(AbstractFieldSchema $items): ArraySchema
```

| Parameter | Type | Description |
|:--|:--|:--|
| `$items` | `AbstractFieldSchema` | Schema definition for each element |

**JSON Schema output:**

```json
{ "type": "array", "items": { ... } }
```

---

## Basic Usage

```php
use SchemableValidator\SV;

// Array of strings (each element: 1–100 characters)
SV::array(SV::string()->min(1)->max(100))

// Array of enum values (multi-select checkboxes)
SV::array(SV::enum(['php', 'js', 'python', 'ruby']))

// Array of integers
SV::array(SV::integer()->min(1))
```

---

## .minItems(n) {#minitems}

Sets the **minimum number of items** in the array.

```php
SV::array($items)->minItems(int $n)
```

**JSON Schema keyword:** `minItems`

```php
// At least one selection required
SV::array(SV::enum(['a', 'b', 'c']))->minItems(1)
```

```json
{ "type": "array", "items": { "type": "string", "enum": ["a","b","c"] }, "minItems": 1 }
```

---

## .maxItems(n) {#maxitems}

Sets the **maximum number of items** in the array.

```php
SV::array($items)->maxItems(int $n)
```

**JSON Schema keyword:** `maxItems`

```php
// Up to 3 selections allowed
SV::array(SV::enum(['a', 'b', 'c', 'd']))->maxItems(3)
```

---

## Implementation Examples

### PHP (Schema Definition)

```php
$schema = SV::object([
  'name'     => SV::string()->min(1)->max(100),
  'tags'     => SV::array(SV::string()->min(1)->max(50))->minItems(1)->maxItems(5)->optional(),
  'interests'=> SV::array(SV::enum(['sports', 'music', 'travel', 'food']))->optional(),
]);
```

### JSON Schema Output

```json
{
  "type": "object",
  "properties": {
    "name":      { "type": "string", "minLength": 1, "maxLength": 100 },
    "tags":      { "type": "array", "items": { "type": "string", "minLength": 1, "maxLength": 50 }, "minItems": 1, "maxItems": 5 },
    "interests": { "type": "array", "items": { "type": "string", "enum": ["sports","music","travel","food"] } }
  },
  "required": ["name"]
}
```

### Server-side Validation

Array fields are submitted via `$_POST` as `tags[]`.

```php
$result = $schema->toValidator()->validate($_POST)->getResult();
```

### Client-side (`@uuki/schemable-validator-client`)

Pass a `Record<string, string | string[]>` to `validateObject`.

```typescript
import { validateObject } from '@uuki/schemable-validator-client'

const result = validateObject(
  { name: 'Alice', tags: ['php', 'js'] },
  schema,
)
```

The `FieldResult.value` for array fields is `string[]`.

```typescript
result.tags
// { value: ['php', 'js'], is_valid: true, errors: null }
```
