# Schemable Validator

[![Packagist](https://img.shields.io/packagist/v/uuki/schemable-validator)](https://packagist.org/packages/uuki/schemable-validator)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-8892BF?logo=php&logoColor=white)](https://packagist.org/packages/uuki/schemable-validator)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D5.9-21759B?logo=wordpress&logoColor=white)](https://wordpress.org/)

A PHP-first validation library for **defining and executing validation constraints on the server**.
Its distinguishing feature is the ability to export those constraints as [JSON Schema draft 2020-12](https://json-schema.org/), making the same rules available to any JavaScript framework on the client without maintaining duplicate definitions across the stack.

The name reflects this: *validator* is the primary role, *schemable* is its defining feature.

---

## 📦 Packages

| Package | Description |
|:--|:--|
| `uuki/schemable-validator` | PHP core library (framework-agnostic) |
| `wp-schemable-validator` | WordPress plugin — REST endpoint, helpers, admin UI |
| `@uuki/schemable-validator-client` | TypeScript client — validates against JSON Schema output |

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

See [docs/installation.md](docs/installation.md) for full setup.

---

## 🚀 Quick Start

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
// GET /wp-json/schv/v1/schema/contact → JSON Schema
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

Or with Zod:

```typescript
import { buildZodSchema } from '@uuki/schemable-validator-client/zod'

const zodSchema = buildZodSchema(schema)
const parsed = zodSchema.safeParse(formData)
```

---

## 🗂️ JSON Schema output

`toJson()` converts the PHP schema definition to JSON Schema draft 2020-12:

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "properties": {
    "name":  { "type": "string", "minLength": 1, "maxLength": 100 },
    "email": { "type": "string", "format": "email" },
    "tel":   { "type": "string", "pattern": "^(0\\d{9,10}|0\\d{1,4}-\\d{1,4}-\\d{3,4})$" },
    "type":  { "type": "string", "enum": ["general", "support", "other"] },
    "body":  { "type": "string", "minLength": 10 }
  },
  "required": ["name", "email", "type", "body"]
}
```

---

## ⚙️ How it works

```
SV::object([...])          ← PHP: single source of truth
    │
    ├─ toValidator()        → server-side validation (NativeAdapter, dependency-free)
    │
    └─ toJson()             → JSON Schema draft 2020-12
           │
           └─ REST endpoint (WordPress)
                  │
                  └─ @uuki/schemable-validator-client  → client-side validation
                     Zod / Valibot / any JS validator
```

Field validation runs through a swappable **backend adapter**.
The default is `NativeAdapter`, which has no external dependencies and mirrors the client-side validation semantics.
Optional adapters (`RespectAdapter`, `OpisAdapter`) can be installed and swapped in without changing the public API.
See [Backend Adapters](docs/backend-adapters.md) for details.

Constraints that cannot be expressed in JSON Schema (file uploads, custom rules) are recorded in `x-unmapped-fields` and handled server-side automatically.

---

## 📖 Documentation

| | |
|:--|:--|
| [Installation](docs/installation.md) | Requirements, package structure |
| [Feature Guide](docs/feature-guide.md) | Validator, file validation, CSRF, reCAPTCHA |
| [Interfaces](docs/interfaces.md) | WordPress helpers, REST API |
| [Schema Builder](docs/schema-builder.md) | `SV::object()` API, JSON Schema output |
| [Backend Adapters](docs/backend-adapters.md) | Swapping the validation engine, custom adapters |
| [Custom Validation](docs/custom-validation.md) | `SV::custom()`, escape hatches, `x-unmapped-fields` |
| [Message Dict](docs/message-dict.md) | Localisation, custom error messages |
| [Development](docs/development.md) | Local playground, conformance suite |

---

## 🔗 Dependencies

The PHP core requires only PHP >= 7.4 and [`symfony/polyfill-php80`](https://packagist.org/packages/symfony/polyfill-php80).
No validation engine is bundled.

Optional engine packages are only loaded when explicitly installed:

- [Respect/Validation](https://packagist.org/packages/respect/validation) ^2.2 — enables `RespectAdapter` and the Respect escape hatches (`SV::respect`, `RespectRules`, `postalCode`, `creditCard`, `iban`)
- [Opis/json-schema](https://packagist.org/packages/opis/json-schema) ^2.6 — enables `OpisAdapter` (strict JSON Schema semantics, no coercion)

---

## 📄 License

This project is open-sourced software licensed under the [MIT License](LICENSE).
