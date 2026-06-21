# SchemaBuilder

`SchemaBuilder` is the central class of Schemable Validator. It lets you define all validation rules in PHP once and use them in two ways simultaneously:

- **Server-side** — convert to a `Validator` (NativeAdapter by default, dependency-free) via `toValidator()`.
- **Client-side** — export to standard JSON Schema (draft 2020-12) via `toJson()` / `toJsonSchema()`, then consume from any JS validator (Zod, Valibot, AJV, …).

| Feature | Description |
|---|---|
| Fluent builder | `SV::string()->email()->min(3)->max(100)` |
| JSON Schema export | `toJson()` / `toJsonSchema()` — standard draft 2020-12 |
| Server validation | `toValidator()->validate($data)->getResult()` |
| Conditional required | `->when('type', SV::equal('company'), ['company_name'])` |
| WordPress REST | `schv_register_schema('/contact', $schema)` — exposes schema as a GET endpoint |
| Unmapped fields | `SV::file()` / `SV::respect()` are tracked in `x-unmapped-fields`, validated server-side only |

## Basic usage

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
| `SV::custom(callable, message)` | - | Dependency-free escape hatch. Returns `CustomFieldSchema`. Recorded in `x-unmapped-fields` |

Modifiers:

| Modifier | Effect |
|:--|:--|
| `.optional()` | Excluded from the `required` array |
| `.nullable()` | Converts `"type"` to an array such as `["string", "null"]` |
| `.serverOnly()` | Excluded from client-facing JSON Schema output entirely. Validated server-side as normal |

::: warning Pattern validation limits
`.pattern()` and Schema Editor pattern fields are evaluated with `preg_match()` (PHP) and `RegExp` (JS).
To guard against ReDoS, inputs longer than **500 characters** skip pattern validation and are treated as valid.
If your field accepts long text (e.g. a textarea), rely on `.min()` / `.max()` for length constraints rather than a pattern.

Schema Editor allows administrators to enter arbitrary regular expressions.
PHP's `pcre.backtrack_limit` (default 1,000,000) prevents infinite backtracking; when the limit is hit, pattern validation is skipped for that field.
:::

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

The TypeScript client passes these fields through as valid.
When `x-unmapped-fields` is present, `validateObject()` emits a console warning listing the field names.
To suppress the warning for fields you expect to be server-only, pass `acknowledgedServerFields`:

```typescript
import { validateObject } from '@uuki/schemable-validator-client'

const result = validateObject(formData, schema, {
  acknowledgedServerFields: ['avatar', 'custom_check'],
})
```

## About `.serverOnly()`

Fields marked `.serverOnly()` are excluded from the JSON Schema output entirely.
They do not appear in `properties`, `required`, or `x-unmapped-fields`.
Server-side validation via `toValidator()` includes them as normal.

```php
$schema = SV::object([
  'email'       => SV::string()->email(),
  'risk_score'  => SV::integer()->min(0)->max(100)->serverOnly(),
]);

$schema->toJson();
// → {"properties": {"email": ...}, "required": ["email"]}
// risk_score is absent — invisible to clients

$schema->toValidator()->validate($data);
// → validates both email and risk_score
```

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
  array $config = []
): Validator
```

| Parameter | Type | Description |
|:--|:--|:--|
| `$config['adapter']` | `BackendAdapter` | Validation engine. Default: `NativeAdapter` (dependency-free) |
| `$config['fileDriver']` | `FileValidationDriver` | File validation driver. Default: `NativeFileValidator` |
| `$config['imageDriver']` | `ImageDriver` | Image constraint driver. Default: `null` (image constraints are skipped) |
| `$config['captchaDriver']` | `CaptchaDriver` | CAPTCHA verification driver. Default: `null` (captcha verification unavailable) |

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

### `mergeJsonSchema()`

Merges an external JSON Schema with the builder's fields.
The external schema supplies primitive fields (defined via the [Schema Editor](/feature-guide#schema-editor) or any JSON Schema source), while the builder supplies fields that require code: file uploads, custom validators, cross-field conditions, and driver injection.

```php
$schema->mergeJsonSchema(array $jsonSchema): self
```

When both sources define the same field name, the builder's definition takes precedence.

```php
use SchemableValidator\SV;
use SchemableValidator\Adapters\Captcha\ReCaptchaV3Driver;
use SchemableValidator\Adapters\Native\NativeImageDriver;

// GUI-defined schema (e.g. from StoredSchemaProvider)
$gui = [
  'type'       => 'object',
  'properties' => [
    'name'  => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100],
    'email' => ['type' => 'string', 'format' => 'email'],
    'type'  => ['type' => 'string', 'enum' => ['personal', 'company']],
  ],
  'required' => ['name', 'email', 'type'],
];

// Code adds what the GUI cannot express
$result = SV::object([
  'avatar' => SV::file(['image/jpeg', 'image/png'], ['maxWidth' => 4096]),
])->mergeJsonSchema($gui)
  ->when('type', SV::equal('company'), ['company_name'])
  ->toValidator([
    'imageDriver'   => new NativeImageDriver(),
    'captchaDriver' => new ReCaptchaV3Driver('SECRET'),
  ])
  ->validate($_POST)
  ->validateFiles($_FILES)
  ->validateCaptcha()
  ->getResult();
```

::: tip WordPress
Use `schv_stored_schema($slug)` to load a schema created via the Schema Editor:
```php
$gui = schv_stored_schema('contact')->toJsonSchema();
$result = SV::object([...])->mergeJsonSchema($gui)->toValidator()->validate($_POST)->getResult();
```
:::

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
