# SV::object() - Object Definition and Output

---

## SV::object(fields) {#object}

Defines a schema from a set of fields. All field definitions are aggregated here.

```php
SV::object(array<string, AbstractFieldSchema> $fields): SchemaBuilder
```

| Parameter | Type | Description |
|:--|:--|:--|
| `$fields` | `array<string, AbstractFieldSchema>` | Associative array of field name → field schema |

**Use case:** The central point where you define the schema for a single form or API request.

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name'  => SV::string()->min(1)->max(100),
  'email' => SV::string()->email(),
  'age'   => SV::integer()->min(0)->optional(),
  'type'  => SV::enum(['general', 'support']),
]);
```

---

## .toJsonSchema() {#tojsonschema}

Returns the schema as a **JSON Schema draft 2020-12 array**.

```php
$schema->toJsonSchema(array $options = []): array
```

| Option | Type | Default | Description |
|:--|:--|:--|:--|
| `metaSchema` | `bool` | `false` | When `true`, includes the `$schema` URI in the output |

- `SV::file()` / `SV::respect()` fields are excluded from `properties` and recorded in `x-unmapped-fields`
- Fields without `optional()` are included in the `required` array

**Use case:** When you need to work with the schema as a PHP array, or manually process a REST response.

```php
$array = $schema->toJsonSchema();
// [
//   '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
//   'type'       => 'object',
//   'properties' => [...],
//   'required'   => [...],
// ]
```

---

## .toJson() {#tojson}

Returns the schema as a **JSON string**. Output uses `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`.

```php
$schema->toJson(): string
```

**Use case:** REST endpoint response body, debug output.

```php
echo $schema->toJson();
```

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "properties": {
    "name":  { "type": "string", "minLength": 1, "maxLength": 100 },
    "email": { "type": "string", "format": "email" },
    "age":   { "type": "integer", "minimum": 0 },
    "type":  { "type": "string", "enum": ["general", "support"] }
  },
  "required": ["name", "email", "type"]
}
```

---

## .toValidator() {#tovalidator}

Generates a **`Validator` instance** from the schema. Default backend is the dependency-free NativeAdapter.  
Can validate all fields including `SV::file()` / `SV::respect()` / `SV::custom()`.

```php
$schema->toValidator(
  array $options = [],
  ?BackendAdapter $adapter = null,
  ?FileValidationDriver $fileDriver = null
): Validator
```

| Parameter | Type | Description |
|:--|:--|:--|
| `$options` | `array` | `Validator` options (e.g. reCAPTCHA configuration) |
| `$adapter` | `?BackendAdapter` | Backend adapter. `null` = NativeAdapter (default) |
| `$fileDriver` | `?FileValidationDriver` | File validation driver. `null` = default driver |

**Use case:** Server-side form validation. Combined with `toJsonSchema()`, avoids maintaining duplicate definitions.

```php
// Text validation
$result = $schema->toValidator()->validate($_POST)->getResult();

// Validation including files
$result = $schema->toValidator()
  ->validate($_POST)
  ->validateFiles($_FILES)
  ->getResult();

// Validation including CAPTCHA
$result = $schema->toValidator([
    'captchaDriver' => new ReCaptchaV3Driver('SECRET'),
  ])
  ->validate($_POST)
  ->validateCaptcha()
  ->getResult();
```

### Return value of getResult()

```json
{
  "name":  { "value": "Alice", "is_valid": true,  "errors": null },
  "email": { "value": "bad",   "is_valid": false, "errors": "\"bad\" must be valid email" }
}
```

---

## .toUiSchema() {#touischema}

Returns a JSON Forms / RJSF compatible UI Schema array.

```php
$schema->toUiSchema(): array
```

---

## .customFields(names) {#customfields}

Declares custom field names via the `x-custom-fields` extension key.

```php
$schema->customFields(array $names): self
```

| Parameter | Type | Description |
|:--|:--|:--|
| `$names` | `string[]` | Array of custom field names |

---

## .when(field, expr, require) {#when}

Makes another field **conditionally required** when a given field satisfies a condition.

```php
$schema->when(string $field, WhenExpr|scalar $expr, array $require): self
```

| Parameter | Type | Description |
|:--|:--|:--|
| `$field` | `string` | The field name to evaluate the condition on |
| `$expr` | `WhenExpr` \| `scalar` | Comparison expression. Passing a scalar is equivalent to `SV::equal($value)` |
| `$require` | `string[]` | Array of field names to make required when the condition is met |

Can be called multiple times.  
**Use case:** "Make company name required when type is 'company'", "Make parental consent required when age is under 18", and similar cross-field dependency rules.

---

### List of Condition Expressions {#when-expressions}

Use the following `SV::*` factories for `$expr`.

| Expression | Comparison | Notes |
|:--|:--|:--|
| `'value'` (scalar) | `=== 'value'` | Shorthand for `SV::equal('value')` |
| `SV::equal($value)` | `===` | String equality |
| `SV::notEqual($value)` | `!==` | String inequality |
| `SV::greaterThanOrEqual($n)` | `>= n` | Numeric, greater than or equal |
| `SV::lessThanOrEqual($n)` | `<= n` | Numeric, less than or equal |
| `SV::greaterThan($n)` | `> n` | Numeric, strictly greater than |
| `SV::lessThan($n)` | `< n` | Numeric, strictly less than |
| `SV::field('name')` | - | Reference another field's value (combine with the above) |

Passing `SV::field('name')` as the argument to `SV::equal()` / `SV::notEqual()` enables **comparison between two fields**.  
Numeric operators (`>=` / `<=` / `>` / `<`) also accept field references.

---

### Usage Examples

#### Scalar shorthand (=== only)

```php
SV::object([
  'type'         => SV::enum(['personal', 'company']),
  'company_name' => SV::string()->min(1)->optional(),
])->when('type', 'company', ['company_name']);
```

#### Explicit === / !==

```php
// Make company_name required when type === 'company'
->when('type', SV::equal('company'), ['company_name'])

// Make note required when role !== 'admin'
->when('role', SV::notEqual('admin'), ['note'])
```

#### Numeric comparison

```php
// Make consent required when age >= 18
->when('age', SV::greaterThanOrEqual(18), ['consent'])

// Make retry required when score <= 50
->when('score', SV::lessThanOrEqual(50), ['retry'])

// Make warn required when qty < 1 (strictly less than)
->when('qty', SV::lessThan(1), ['warn'])

// Make bonus required when level > 10 (strictly greater than)
->when('level', SV::greaterThan(10), ['bonus'])
```

#### Field reference (comparison between two fields)

```php
// Make hint required when password === confirm_password
->when('password', SV::equal(SV::field('confirm_password')), ['hint'])

// Make change_reason required when new_password !== old_password
->when('new_password', SV::notEqual(SV::field('old_password')), ['change_reason'])

// Make note required when price >= min_price
->when('price', SV::greaterThanOrEqual(SV::field('min_price')), ['note'])
```

#### Multiple conditions

```php
SV::object([...])->
  when('plan', SV::equal('enterprise'), ['billing_email'])->
  when('plan', SV::equal('enterprise'), ['contract_name']);
```

---

### JSON Schema Output {#when-json-schema}

All conditions are output under the `x-when` extension key. Literal `===` conditions are **also written** as standard `if/then` (single) or `allOf` (multiple).

```json
{
  "x-when": [
    { "field": "type",     "op": "===", "equals": "company", "require": ["company_name"] },
    { "field": "age",      "op": ">=",  "equals": 18,        "require": ["consent"] },
    { "field": "password", "op": "===", "equalsField": "confirm_password", "require": ["hint"] }
  ],
  "if":   { "properties": { "type": { "const": "company" } } },
  "then": { "required": ["company_name"] }
}
```

| Key | Content |
|:--|:--|
| `field` | Source field name |
| `op` | `===` / `!==` / `>=` / `<=` / `>` / `<` |
| `equals` | Literal value (when `equalsField` is absent) |
| `equalsField` | Target field name (when `SV::field()` is used) |
| `require` | Array of field names to make required when the condition is met |

> `@uuki/schemable-validator-client`'s `validateObject` evaluates `x-when` first. If `x-when` is absent, it falls back to standard `if/then` / `allOf`.

---

## Registering with a WordPress REST Endpoint

```php
// GET /wp-json/schv/v1/schema/contact → returns JSON Schema
schv_register_schema('/schema/contact', $schema);
```

```php
// Get the URL
$url = schv_schema_url('/schema/contact');
// → https://example.com/wp-json/schv/v1/schema/contact
```

ETag and `Cache-Control: public, max-age=3600` are added automatically.

---

## Example: A Single Schema for Everything

```php
$schema = SV::object([
  'name'   => SV::string()->min(1)->max(100),
  'email'  => SV::string()->email(),
  'tel'    => SV::string()->pattern('^0\d{9,10}$')->optional(),
  'type'   => SV::enum(['general', 'support', 'other']),
  'body'   => SV::string()->min(10)->max(1000),
  'avatar' => SV::file(['image/jpeg', 'image/png'])->optional(),
]);

// 1. Expose via REST
schv_register_schema('/schema/contact', $schema);

// 2. Server-side validation
add_action('template_redirect', function () use ($schema) {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

  $result = $schema->toValidator()
    ->validate($_POST)
    ->validateFiles($_FILES)
    ->getResult();
});
```
