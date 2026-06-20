# Schemable Validator Core

[![Packagist](https://img.shields.io/packagist/v/uuki/schemable-validator-core)](https://packagist.org/packages/uuki/schemable-validator-core)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-8892BF?logo=php&logoColor=white)](https://packagist.org/packages/uuki/schemable-validator-core)

A dependency-free PHP form validation library.
Define constraints once with a fluent API, validate server-side with zero external packages, and optionally export the same rules as JSON Schema for client-side consumption.

## Features

- **Server-side validation with no dependencies.**
  The default engine (`NativeAdapter`) requires nothing beyond PHP 7.4.
  `$_POST` string values are automatically coerced to the declared types via the built-in Coercion Contract.

- **Client-side sync when you need it.**
  Call `toJson()` to produce a JSON Schema (draft 2020-12) document.
  Built-in Zod and Valibot adapters convert it into native client schemas.

- **Pluggable drivers.**
  CAPTCHA verification (reCAPTCHA v3, hCaptcha, Cloudflare Turnstile), file upload validation, image constraint checks, and CSRF protection are injected through driver interfaces.

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

## Architecture

```
SV::object([...])
  ├─ toValidator()          → server-side validation (NativeAdapter, zero deps)
  └─ toJson()               → JSON Schema (draft 2020-12)
        ├─ AJV              (direct consumption)
        ├─ Zod adapter
        └─ Valibot adapter
```

The validation engine is swappable.
Pass a `RespectAdapter` or `OpisAdapter` in the config to use a different backend; the public API and the `{value, is_valid, errors}` result shape stay the same.

## Installation

```shell
composer require uuki/schemable-validator
```

Optional engine packages (only loaded when installed):

- [respect/validation](https://packagist.org/packages/respect/validation) ^2.2 — enables `RespectAdapter`
- [opis/json-schema](https://packagist.org/packages/opis/json-schema) ^2.6 — enables `OpisAdapter`

## Documentation

Full documentation: [uuki.github.io/schemable-validator](https://uuki.github.io/schemable-validator/)

| | |
|:--|:--|
| [Overview](https://uuki.github.io/schemable-validator/overview) | Features, architecture |
| [Installation](https://uuki.github.io/schemable-validator/installation) | Requirements, package structure |
| [SchemaBuilder](https://uuki.github.io/schemable-validator/schema-builder) | Fluent API, JSON Schema output |
| [Feature Guide](https://uuki.github.io/schemable-validator/feature-guide) | Validator, file validation, CAPTCHA, CSRF |
| [Backend Adapters](https://uuki.github.io/schemable-validator/backend-adapters) | Swapping the validation engine |

## License

[MIT](../../LICENSE)
