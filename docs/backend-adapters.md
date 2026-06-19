# Backend Adapters

The PHP core runs field validation through a swappable **backend adapter**.
The validation engine can be changed or replaced without affecting the public API or the `{value, is_valid, errors}` result shape.

---

## Interface

```php
// packages/core/Validation/BackendAdapter.php
interface BackendAdapter {
    public function compile(array $jsonSchema, ?MessageDict $dict = null): ExecutableValidator;
}

// packages/core/Validation/ExecutableValidator.php
interface ExecutableValidator {
    // returns array<string, array{value: mixed, is_valid: bool, errors: ?string}>
    public function validate(array $data): array;
}
```

`compile()` takes a JSON Schema 2020-12 object (`properties` / `required`, plus the `x-*` extensions) and returns an `ExecutableValidator`.
The executable validates each field and returns the common result shape.
`x-transform` and `x-when` are applied by the caller (`Validator` / the conformance runners), not by the executable itself.

Each adapter translates its engine's failures into a neutral rule id and resolves error text via the shared catalog (see [MessageDict](./message-dict.md)).
All backends produce identical error strings for the same violation.

---

## Using an adapter

### Default behaviour

When no adapter is passed, `NativeAdapter` is used automatically.
It has no third-party dependencies and matches the FE validation semantics, so most projects need nothing else.

```php
$result = SV::object([
    'name'  => SV::string()->min(2),
    'email' => SV::string()->email(),
])->toValidator()->validate($_POST);
```

### Using a specific adapter

Pass an adapter instance as `'adapter'` in the second argument to `toValidator()`.
The adapter replaces `NativeAdapter` for all mappable fields.

#### Using RespectAdapter

Install the package first:

```
composer require respect/validation
```

Then pass `RespectAdapter` to `toValidator()`:

```php
use SchemableValidator\Validation\Adapters\RespectAdapter;

$validator = SV::object([
    'name' => SV::string()->min(2),
    'age'  => SV::integer(),
])->toValidator([], ['adapter' => new RespectAdapter()]);

$result = $validator->validate(['name' => 'Alice', 'age' => '30'])->getResult();
// $result['age']['is_valid'] === true  (form strings are still coerced)
```

Internally, the SV fluent API is first converted to JSON Schema (the neutral IR) inside `toValidator()`. `RespectAdapter` then maps each JSON Schema keyword to a Respect rule before running validation.

```
SV::string()->min(2)->email()
      ↓ toJsonSchema()
{ "type": "string", "minLength": 2, "format": "email" }
      ↓ RespectAdapter::compile()
v::stringType() + v::length(2, null) + v::email()
      ↓
Respect/Validation validates
```

Form strings are still accepted per Coercion Contract v1, so `"30"` passes `integer` just as it does with `NativeAdapter`.

#### Using OpisAdapter

Install the package first:

```
composer require opis/json-schema
```

Then pass `OpisAdapter` to `toValidator()`:

```php
use SchemableValidator\Validation\Adapters\OpisAdapter;

$validator = SV::object(['count' => SV::integer()])
    ->toValidator([], ['adapter' => new OpisAdapter()]);

$result = $validator->validate(json_decode($body, true))->getResult();
// a string like "5" fails type: integer — no coercion
```

Internally, `OpisAdapter` passes the JSON Schema directly to opis/json-schema with no intermediate conversion.
Unlike `RespectAdapter`, there is no step that maps JSON Schema keywords to engine-specific rules.

```
SV::object(['count' => SV::integer()])
      ↓ toJsonSchema()
{ "type": "object", "properties": { "count": { "type": "integer" } } }
      ↓ OpisAdapter::compile()
opis/json-schema validates the JSON Schema directly
      ↓
strict validation (no coercion)
```

A form string like `"5"` fails `type: integer`, where the other adapters would accept it.
Use it when the input is already typed JSON rather than `$_POST` strings.

#### Via `Validator::fromJsonSchema()`

When constructing from a raw JSON Schema, pass the adapter as the **fifth argument**:

```php
use SchemableValidator\Validator;
use SchemableValidator\Validation\Adapters\OpisAdapter;

$validator = Validator::fromJsonSchema($jsonSchema, [], [], null, new OpisAdapter());
```

---

## Injecting validation drivers

Some validation logic cannot be expressed in JSON Schema: file upload checking, for example, requires filesystem access and MIME detection.
The core is designed so that this kind of logic can be injected as a **driver**, keeping the core itself free of system-level dependencies while allowing the behaviour to be replaced in test or production contexts.

The default **file validation driver** is `NativeFileValidator`.
It uses PHP's `finfo` extension to detect MIME types against an allow-list and has no external dependencies.

### Injecting a custom file validation driver

Pass a `FileValidationDriver` implementation as `'fileDriver'` in the second argument to `toValidator()`:

```php
use SchemableValidator\Validation\FileValidationDriver;

final class MyS3FileValidator implements FileValidationDriver {
    public function validate(array $file, array $config): array {
        // $file: {name, type, tmp_name, error, size}
        // $config: ['accept' => ['image/jpeg', ...]]
        // return: {value, is_valid, errors}
    }
}

$validator = SV::object(['avatar' => SV::file()])
    ->toValidator([], ['fileDriver' => new MyS3FileValidator()]);
```

Both keys are independent and can be combined:

```php
$validator = SV::object([
    'name'   => SV::string()->min(2),
    'avatar' => SV::file(),
])->toValidator([], [
    'adapter'    => new RespectAdapter(),
    'fileDriver' => new MyS3FileValidator(),
]);
```

### Injecting an image driver

`FileValidationDriver` checks MIME types but does not inspect pixel dimensions or byte size.
`ImageDriver` covers that: it runs after MIME acceptance and validates the image's dimensions and file size.

Pass image constraints as the second argument to `SV::file()`, then inject a driver via `'imageDriver'`:

```php
use SchemableValidator\SV;
use SchemableValidator\Validation\NativeImageDriver;

$schema = SV::object([
    'avatar' => SV::file(
        ['image/jpeg', 'image/png', 'image/webp'],
        [
            'maxWidth'  => 2048,
            'maxHeight' => 2048,
            'maxSize'   => 5 * 1024 * 1024, // 5 MB in bytes
        ]
    ),
]);

$result = $schema
    ->toValidator([], ['imageDriver' => new NativeImageDriver()])
    ->validate($_POST)
    ->validateFiles($_FILES)
    ->getResult();
```

`NativeImageDriver` reads the image header via `getimagesize()` — no pixel data is decoded and no external dependency is needed.
File size is checked before header parsing, so oversized files are rejected without reading the image.

Supported constraint keys:

| Key | Type | Description |
|:--|:--|:--|
| `maxWidth` | `int` | Maximum width in pixels |
| `maxHeight` | `int` | Maximum height in pixels |
| `minWidth` | `int` | Minimum width in pixels |
| `minHeight` | `int` | Minimum height in pixels |
| `maxSize` | `int` | Maximum file size in bytes |

The `imageDriver` runs only when the file has already passed `fileDriver` (MIME accepted) and the field declares at least one image constraint.
A file field with no image constraints ignores the driver, so a single `NativeImageDriver` instance can be shared across a schema that mixes image and non-image file fields.

### Injecting a CAPTCHA driver

Pass a `CaptchaDriver` as `'captchaDriver'`, then call `validateCaptcha()` on the resulting validator.
Three providers are built in.

```php
use SchemableValidator\Validation\Captcha\ReCaptchaV3Driver;
use SchemableValidator\Validation\Captcha\HCaptchaDriver;
use SchemableValidator\Validation\Captcha\TurnstileDriver;
use SchemableValidator\Validation\Captcha\NullCaptchaDriver;

// Google reCAPTCHA v3
$validator = $schema->toValidator([], [
    'captchaDriver' => new ReCaptchaV3Driver('YOUR_SECRET'),
]);

// hCaptcha
$validator = $schema->toValidator([], [
    'captchaDriver' => new HCaptchaDriver('YOUR_SECRET'),
]);

// Cloudflare Turnstile
$validator = $schema->toValidator([], [
    'captchaDriver' => new TurnstileDriver('YOUR_SECRET'),
]);

// Tests and local development (always passes; pass false to simulate a rejection)
$validator = $schema->toValidator([], [
    'captchaDriver' => new NullCaptchaDriver(),
]);
```

Call `validateCaptcha()` after `validate()`:

```php
$result = $validator
    ->validate($_POST)
    ->validateCaptcha(['action' => 'contact']) // optional action check (reCAPTCHA v3 only)
    ->getResult();
```

The token is read from whichever of these POST fields is present: `recaptcha_token`, `g-recaptcha-response`, `h-captcha-response`, or `cf-turnstile-response`.

The result is written under `$result['captcha']`:

```json
{ "value": 0.9, "is_valid": true, "errors": null }
```

`value` holds the reCAPTCHA v3 score (0.0–1.0) and is `null` for providers that return no score.

`ReCaptchaV3Driver` accepts optional parameters for the score threshold and endpoint:

```php
new ReCaptchaV3Driver(
    secret:   'YOUR_SECRET',
    minScore: 0.5,   // default 0.5; reject responses below this score
    endpoint: 'https://www.recaptcha.net/recaptcha/api/siteverify', // alternative Google endpoint
)
```

The endpoint must be one of the two official Google siteverify URLs; any other value throws at construction time.

**Security properties.**
All three built-in drivers send verification requests through `CurlController`, which enforces HTTPS, disables redirect following, blocks private and reserved IP addresses (RFC 1918 / loopback / link-local for IPv4; ULA / loopback / multicast / link-local / IPv4-mapped for IPv6), and applies a 30-second timeout.
Each driver hardcodes its verification endpoint, so no caller-supplied URL reaches the network.
Internal error details (endpoint URL, error codes from the provider) are written to `error_log()` only; callers receive the generic message `"CAPTCHA verification failed"`.

**Legacy path.**
The `validateReCaptcha()` method and the `recaptcha_*` constructor options still work and are unchanged.
New code should prefer `captchaDriver` + `validateCaptcha()`.

```php
// Legacy (still works)
$validator = new Validator($schema, [
    'recaptcha_secret'      => 'YOUR_SECRET',
    'recaptcha_valid_score' => 0.5,
]);
$result = $validator->validate($_POST)->validateReCaptcha()->getResult();

// Preferred
$validator = $schema->toValidator([], [
    'captchaDriver' => new ReCaptchaV3Driver('YOUR_SECRET'),
]);
$result = $validator->validate($_POST)->validateCaptcha()->getResult();
```

---

## Built-in adapters

| Adapter | Dependency | Coercion Contract v1 | Use case |
|:--|:--|:--|:--|
| `NativeAdapter` (default) | none | yes | No external dependencies; matches FE semantics |
| `RespectAdapter` | `respect/validation` (optional) | yes | Respect/Validation as the constraint engine |
| `OpisAdapter` | `opis/json-schema` (optional) | no (strict JSON Schema) | Typed JSON input; structural validation |

**NativeAdapter** is the default. `SchemaBuilder::toValidator()` and `Validator::fromJsonSchema()` both use it automatically.
It ports the FE `constraint.ts`/`validator.ts` behaviour to PHP with no third-party dependencies, and accepts form strings such as `"42"` for `integer` fields (Coercion Contract v1).
The conformance suite verifies it against all `conformance/*.json` fixtures (`tests/Conformance/NativeConformanceTest.php`).

**RespectAdapter** must be passed explicitly.
The Respect escape hatches (`SV::respect` / `RespectRules`) and raw `v` schemas also use it internally, regardless of which adapter is set for mappable fields.
Requires the optional `respect/validation` package.

**OpisAdapter** applies strict JSON Schema semantics without coercion.
A form string like `"42"` fails `type: integer`, where `NativeAdapter` and `RespectAdapter` would accept it.

---

## Optional dependencies

The default configuration (NativeAdapter, NativeFileValidator, and `SV::custom`) requires no external packages.
Both engine packages are listed as composer `suggest` and are only loaded when explicitly installed:

- `respect/validation`: enables `RespectAdapter`, the Respect escape hatches (`RespectRules`, `SV::respect`, `postalCode`, `creditCard`, `iban`), and raw `v` schemas.
  Respect's factory is initialised lazily and is never loaded when using the Native default.

  ```
  composer require respect/validation
  ```

- `opis/json-schema`: enables `OpisAdapter`.
  Constructing it without the package installed throws a descriptive runtime error.

  ```
  composer require opis/json-schema
  ```

---

## Writing a custom adapter

```php
use SchemableValidator\Validation\BackendAdapter;
use SchemableValidator\Validation\ExecutableValidator;

final class MyAdapter implements BackendAdapter {
    public function compile(array $jsonSchema, ?MessageDict $dict = null): ExecutableValidator {
        // validate(array $data) must return
        // [field => ['value' => ..., 'is_valid' => bool, 'errors' => ?string]]
        return new MyExecutableValidator(/* ... */);
    }
}

$validator = Validator::fromJsonSchema($jsonSchema, [], [], null, new MyAdapter());
```

For consistent error messages across backends, map failures to the neutral rule vocabulary and resolve text via `DefaultMessages` / `MessageDict::interpolate()` rather than returning engine-specific strings.

---

## Frontend adapters

The client package includes native validation and optional Zod and Valibot adapters as subpath exports (`@uuki/schemable-validator-client/zod`, `/valibot`).
Third-party adapters for frameworks such as Svelte and React Hook Form consume the same JSON Schema and `x-*` contract.
See [client adapter docs](./client-adapter.md).

---

## `x-*` extensions and `$vocabulary`

The extension keywords (`x-when`, `x-custom-fields`, `x-transform`, inline `errorMessage`) are kept as `x-*` rather than promoted to a formal JSON Schema `$vocabulary`.

- Generic JSON Schema validators ignore `x-*` keys without error, so schemas remain portable.
- Promotion to `$vocabulary` would only be considered if an external consumer silently under-validates schemas by ignoring `x-when` or `x-custom-fields`.
- If promoted, the `x-*` spellings will remain as aliases for at least one major version.

The PHP backends and the FE evaluator both implement the semantics of these extensions directly, which enables the engine-neutral message guarantee and the cross-stack conformance suite.
