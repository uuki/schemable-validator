# SchemaBuilder

`SchemaBuilder` is the central class of Schemable Validator. It lets you define all validation rules in PHP once and use them in two ways simultaneously:

- **Server-side** — convert to a `Validator` (NativeAdapter by default, dependency-free) via `toValidator()`.
- **Client-side** — export to standard JSON Schema (draft 2020-12) via `toJson()` / `toJsonSchema()`, then consume from any JS validator (Zod, Valibot, AJV, …).

### Key features

| Feature | Description |
|---|---|
| Fluent builder | `SV::string()->email()->min(3)->max(100)` |
| JSON Schema export | `toJson()` / `toJsonSchema()` — standard draft 2020-12 |
| Server validation | `toValidator()->validate($data)->getResult()` |
| Conditional required | `->when('type', SV::equal('company'), ['company_name'])` |
| WordPress REST | `schv_register_schema('/contact', $schema)` — exposes schema as a GET endpoint |
| Unmapped fields | `SV::file()` / `SV::respect()` are tracked in `x-unmapped-fields`, validated server-side only |

### Minimal example

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name'  => SV::string()->min(1)->max(100),
  'email' => SV::string()->email(),
  'tel'   => SV::string()->pattern('^0\d{9,10}$')->optional(),
]);

// Server-side validation
$result = $schema->toValidator()->validate($_POST)->getResult();

// Export for JS clients
header('Content-Type: application/json');
echo $schema->toJson();
```

---

## Field Type Reference

| Method | JSON Schema `type` | Notes |
|:--|:--|:--|
| `SV::string()` | `"string"` | `.email()` `.url()` `.min()` `.max()` `.pattern()` |
| `SV::integer()` | `"integer"` | `.min()` `.max()` |
| `SV::number()` | `"number"` | `.min()` `.max()` (int/float) |
| `SV::boolean()` | `"boolean"` | |
| `SV::enum(['a','b'])` | `"string"` + `enum` | |
| `SV::file(['image/jpeg'])` | - | Cannot be converted to JSON Schema. Recorded in `x-unmapped-fields` |
| `SV::respect(v::...)` | - | **@deprecated** — use `SV::custom()` or `RespectRules::rule()` instead. Cannot be converted to JSON Schema. Recorded in `x-unmapped-fields` |
| `SV::custom(callable, message)` | - | Dependency-free escape hatch. Returns `CustomFieldSchema`. Recorded in `x-unmapped-fields` |

Modifiers:

| Modifier | Effect |
|:--|:--|
| `.optional()` | Excluded from the `required` array |
| `.nullable()` | Converts `"type"` to an array such as `["string", "null"]` |

---

## Example 1: Contact Form

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name'  => SV::string()->min(1)->max(100),
  'email' => SV::string()->email(),
  'tel'   => SV::string()->pattern('^(0\d{9,10}|0\d{1,4}-\d{1,4}-\d{3,4})$')->optional(),
  'type'  => SV::enum(['general', 'support', 'sales', 'other']),
  'body'  => SV::string()->min(10),
]);

echo $schema->toJson();
```

Output:

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "properties": {
    "name": {
      "type": "string",
      "minLength": 1,
      "maxLength": 100
    },
    "email": {
      "type": "string",
      "format": "email"
    },
    "tel": {
      "type": "string",
      "pattern": "^(0\\d{9,10}|0\\d{1,4}-\\d{1,4}-\\d{3,4})$"
    },
    "type": {
      "type": "string",
      "enum": ["general", "support", "sales", "other"]
    },
    "body": {
      "type": "string",
      "minLength": 10
    }
  },
  "required": ["name", "email", "type", "body"]
}
```

`tel` is not included in `required` because it has `.optional()`.

---

## Example 2: User Profile (nullable / file)

```php
$schema = SV::object([
  'username' => SV::string()->min(3)->max(20)->pattern('^[a-zA-Z0-9_]+$'),
  'age'      => SV::integer()->min(0)->max(150)->optional(),
  'score'    => SV::number()->min(0.0)->max(5.0)->optional(),
  'active'   => SV::boolean(),
  'website'  => SV::string()->url()->nullable()->optional(),
  'avatar'   => SV::file(['image/jpeg', 'image/png', 'image/webp'])->optional(),
]);
```

Output:

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "properties": {
    "username": {
      "type": "string",
      "minLength": 3,
      "maxLength": 20,
      "pattern": "^[a-zA-Z0-9_]+$"
    },
    "age": {
      "type": "integer",
      "minimum": 0,
      "maximum": 150
    },
    "score": {
      "type": "number",
      "minimum": 0,
      "maximum": 5
    },
    "active": {
      "type": "boolean"
    },
    "website": {
      "type": ["string", "null"],
      "format": "uri"
    }
  },
  "required": ["username", "active"],
  "x-unmapped-fields": ["avatar"]
}
```

- `website` becomes `"type": ["string", "null"]` due to `.nullable()`
- `avatar` uses `SV::file()` (which has no corresponding JSON Schema keyword), so it is excluded from `properties` and recorded in `x-unmapped-fields`

---

## About `x-unmapped-fields`

Fields that cannot be converted to JSON Schema (file uploads, custom callables, etc.)
are recorded by name only under the `x-unmapped-fields` extension key.
Validation is performed via the BackendAdapter (NativeAdapter by default) through `toValidator()`.

```php
// Use as JSON Schema
$jsonSchema = $schema->toJsonSchema();                       // array
$jsonMeta   = $schema->toJsonSchema(['metaSchema' => true]); // array (includes $schema URI)
$json       = $schema->toJson();                             // string

// Use as a validator (includes file fields)
$validator = $schema->toValidator();
$result    = $validator->validate($_POST)->validateFiles($_FILES)->getResult();
```

### `toValidator()` parameters

```php
$schema->toValidator(
  array $options = [],
  ?BackendAdapter $adapter = null,
  ?FileValidationDriver $fileDriver = null
): Validator
```

| Parameter | Type | Description |
|:--|:--|:--|
| `$options` | `array` | Validator options (e.g. reCAPTCHA configuration) |
| `$adapter` | `?BackendAdapter` | Backend adapter for validation. `null` = NativeAdapter (default, dependency-free) |
| `$fileDriver` | `?FileValidationDriver` | File validation driver. `null` = default driver |

### `toJsonSchema()` options

```php
$schema->toJsonSchema(array $options = []): array
```

| Option | Type | Default | Description |
|:--|:--|:--|:--|
| `metaSchema` | `bool` | `false` | When `true`, includes the `$schema` URI in the output |

### `toUiSchema()`

Returns a JSON Forms / RJSF compatible UI Schema array.

```php
$uiSchema = $schema->toUiSchema(); // array
```

### `customFields()`

Declares custom field names via the `x-custom-fields` extension key.

```php
$schema->customFields(array $names): self
```

---

## `toValidator()` Output Example

`toValidator()` returns a `SchemableValidator\Validator`.
Use `validate()` + `getResult()` to retrieve the validation result for each field.

```php
$schema    = SV::object(['name' => SV::string()->min(1)->max(100), 'email' => SV::string()->email()]);
$validator = $schema->toValidator();

// Valid input
$result = $validator->validate(['name' => 'Alice', 'email' => 'alice@example.com'])->getResult();
```

```json
{
  "name":  { "value": "Alice",             "errors": null, "is_valid": true },
  "email": { "value": "alice@example.com", "errors": null, "is_valid": true }
}
```

```php
// Invalid input
$result = $validator->validate(['name' => '', 'email' => 'not-an-email'])->getResult();
```

```json
{
  "name":  { "value": "",             "errors": "\"\" must have a length between 1 and 100", "is_valid": false },
  "email": { "value": "not-an-email", "errors": "\"not-an-email\" must be valid email",         "is_valid": false }
}
```

---

## Registering a WordPress REST Endpoint

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name'  => SV::string()->min(1)->max(100),
  'email' => SV::string()->email(),
]);

// GET /wp-json/schv/v1/contact → returns JSON Schema
schv_register_schema('/contact', $schema);
```
