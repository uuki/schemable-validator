# Overview

Schemable Validator is a dependency-free PHP form validation library.
A fluent API defines constraints once; the default engine validates server-side with zero external packages.
The same definition can be exported as JSON Schema for client-side consumption.

## Features

- **Server-side validation with no dependencies.**
  The default engine (`NativeAdapter`) requires nothing beyond PHP 7.4.
  `$_POST` string values are automatically coerced to the declared types (integer, number, boolean) via the built-in Coercion Contract, so form submissions work without manual casting.

- **Client-side sync when you need it.**
  Call `toJson()` on the same schema to produce a JSON Schema (draft 2020-12) document.
  Built-in Zod and Valibot adapters convert it into native client schemas; AJV can consume it directly.
  When a rule changes in PHP, the client picks it up automatically.

- **Pluggable drivers for cross-cutting concerns.**
  CAPTCHA verification (reCAPTCHA v3, hCaptcha, Cloudflare Turnstile), file upload validation, image constraint checks, and CSRF protection are injected through driver interfaces.
  Swap providers with a one-line config change.

## Architecture

```
PHP (SchemaBuilder)
  └─ SV::object([ 'name' => SV::string()->min(1), ... ])
        │
        ├─ toValidator()          → server-side validation (NativeAdapter, zero deps)
        │
        └─ toJson() / toJsonSchema()
              │
              JSON Schema (draft 2020-12)
              │
              ├─ AJV          (direct consumption)
              ├─ Zod adapter  (sv(jsonSchema).build())
              └─ Valibot adapter
```

The validation engine is swappable.
Pass a `RespectAdapter` or `OpisAdapter` in the config to use a different backend; the public API and the `{value, is_valid, errors}` result shape stay the same.

Rules that fall outside the adapter's mapping scope (file fields, cross-field constraints, and custom logic) are filled in through extension points: `.extend()`, `.refine()`, and `SV::custom()`.
The boundary is explicit: unmappable fields are listed in `x-unmapped-fields` so clients know exactly which rules require server-side validation.

## Quick start

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name'  => SV::string()->min(1)->max(100),
  'email' => SV::string()->email(),
]);

// Server-side validation (no external dependencies)
$result = $schema->toValidator()->validate($_POST)->getResult();

// Export for client-side consumption
echo $schema->toJson();
```

For the full API, see [SchemaBuilder](./schema-builder.md) and [Feature Guide](./feature-guide.md).
