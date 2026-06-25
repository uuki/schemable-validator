# Schemable Validator

[![Packagist](https://img.shields.io/packagist/v/uuki/schemable-validator)](https://packagist.org/packages/uuki/schemable-validator)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-8892BF?logo=php&logoColor=white)](https://packagist.org/packages/uuki/schemable-validator)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D5.9-21759B?logo=wordpress&logoColor=white)](https://wordpress.org/)

A dependency-free PHP form validation library.
Define constraints once with a fluent API, validate server-side with zero external packages, and optionally export the same rules as JSON Schema for client-side consumption.

## ✨ Features

- **Define once, use everywhere.**
  Server-side validation rules act as the single source of truth.
  Export them as JSON Schema (draft 2020-12) via `toJson()` and share the same constraints with any frontend — built-in Zod and Valibot adapters included.
  Rules are defined through a fluent method-chain API.

- **Pluggable engine and driver architecture.**
  The built-in engine (`NativeAdapter`) provides a fluent-interface-style API with zero dependencies.
  Swap in `RespectAdapter` or any custom `BackendAdapter` when you need a different validation engine.
  Domain-specific logic — phone numbers, postal codes, image constraints, CAPTCHA (reCAPTCHA v3, hCaptcha, Turnstile) — is implemented as injectable drivers and can be replaced independently.

- **Zero dependencies, framework-agnostic.**
  Built on plain PHP and TypeScript with no framework coupling.
  Reduces dependency lock-in and keeps the library easy to remove when your project outgrows it.

---

## 📦 Packages

| Package | Description |
|:--|:--|
| [`uuki/schemable-validator`](https://packagist.org/packages/uuki/schemable-validator) | PHP core library (framework-agnostic, zero dependencies) |
| `wp-schemable-validator` | WordPress plugin — REST endpoint, helpers, Schema Editor admin UI |
| [`@uuki/schemable-validator-client`](https://www.npmjs.com/package/@uuki/schemable-validator-client) | TypeScript client — validates against JSON Schema output |

---

## 🚀 Quick start

### 1. Define constraints (PHP)

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name'  => SV::string()->min(1)->max(100),
  'email' => SV::string()->email(),
  'tel'   => SV::string()->pattern('^(0\d{9,10}|0\d{1,4}-\d{1,4}-\d{3,4})$')->optional(),
  'type'  => SV::enum(['general', 'support', 'other']),
  'body'  => SV::string()->min(10),
]);
```

### 2. Server-side validation

```php
$result = $schema->toValidator()->validate($_POST)->getResult();
// { "name": { "value": "...", "is_valid": true, "errors": null }, ... }
```

### 3. Expose as REST endpoint (WordPress)

```php
// GET /wp-json/schv/v1/schema/contact -> JSON Schema
schv_register_schema('/schema/contact', $schema);
```

### 4. Client-side validation (TypeScript)

```typescript
import { validateObject, isAllValid, extractErrors } from '@uuki/schemable-validator-client'

const schema = await fetch('/wp-json/schv/v1/schema/contact').then(r => r.json())
const result = validateObject(formData, schema)

if (!isAllValid(result)) {
  console.log(extractErrors(result))
}
```

Or with Zod / Valibot adapters:

```typescript
import { toZodSchema } from '@uuki/schemable-validator-client/zod'

const zodSchema = toZodSchema(schema)
const parsed = zodSchema.safeParse(formData)
```

---

## ⚙️ Architecture

```
SV::object([...])          <- PHP: single source of truth
    |
    +- toValidator()        -> server-side validation (NativeAdapter, dependency-free)
    |
    +- toJson()             -> JSON Schema draft 2020-12
           |
           +- REST endpoint (WordPress)
                  |
                  +- @uuki/schemable-validator-client  -> client-side validation
                     Zod / Valibot / any JS validator
```

Constraints that cannot be expressed in JSON Schema (file uploads, custom rules) are recorded in `x-unmapped-fields` and validated server-side only.

---

## 🔧 Installation

```shell
# PHP core
composer require uuki/schemable-validator

# WordPress plugin
cd packages/wp-schemable-validator && composer install --no-dev

# TypeScript client
npm install @uuki/schemable-validator-client
```

See [Installation](https://uuki.github.io/schemable-validator/installation) for full setup.

---

## 📖 Documentation

Full documentation: [uuki.github.io/schemable-validator](https://uuki.github.io/schemable-validator/)

| | |
|:--|:--|
| [Overview](https://uuki.github.io/schemable-validator/overview) | Features, architecture |
| [Installation](https://uuki.github.io/schemable-validator/installation) | Requirements, package structure |
| [SchemaBuilder](https://uuki.github.io/schemable-validator/schema-builder) | Fluent API, JSON Schema output, `mergeJsonSchema()` |
| [Feature Guide](https://uuki.github.io/schemable-validator/feature-guide) | Validator, file validation, CAPTCHA, CSRF |
| [Backend Adapters](https://uuki.github.io/schemable-validator/backend-adapters) | Swapping the validation engine, custom adapters |
| [Custom Validation](https://uuki.github.io/schemable-validator/custom-validation) | `SV::custom()`, escape hatches, `x-unmapped-fields` |
| [MessageDict](https://uuki.github.io/schemable-validator/message-dict) | Localisation, custom error messages |

---

## 🔗 Dependencies

The PHP core requires only PHP >= 7.4 and [`symfony/polyfill-php80`](https://packagist.org/packages/symfony/polyfill-php80).
No validation engine is bundled by default.

Optional engine packages are only loaded when explicitly installed:

- [respect/validation](https://packagist.org/packages/respect/validation) ^2.2 — enables `RespectAdapter` and the Respect driver (`RespectRules`, `postalCode`, `creditCard`, `iban`)
- [opis/json-schema](https://packagist.org/packages/opis/json-schema) ^2.6 — enables `OpisAdapter` (strict JSON Schema semantics, no coercion)

---

## 📄 License

This project is open-sourced software licensed under the [MIT License](LICENSE).
