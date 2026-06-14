# Schemable Validator

[![Packagist](https://img.shields.io/packagist/v/uuki/schemable-validator)](https://packagist.org/packages/uuki/schemable-validator)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-8892BF?logo=php&logoColor=white)](https://packagist.org/packages/uuki/schemable-validator)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D5.9-21759B?logo=wordpress&logoColor=white)](https://wordpress.org/)

A PHP-first validation library whose core purpose is **defining and executing validation constraints on the server**. Its distinguishing feature is the ability to export those constraints as [JSON Schema draft 2020-12](https://json-schema.org/), making the same rules available to any JavaScript framework on the client — without maintaining duplicate definitions across the stack.

The name reflects this: *validator* is the primary role, *schemable* is its defining feature.

---

## 📦 Packages

| Package | Description |
|:--|:--|
| `uuki/schemable-validator` | PHP core library (framework-agnostic) |
| `wp-schemable-validator` | WordPress plugin — REST endpoint, helpers, admin UI |
| `@schemable-validator/client` | TypeScript client — validates against JSON Schema output |

---

## 🔧 Installation

```shell
# PHP core
composer require uuki/schemable-validator

# WordPress plugin
cd packages/wp-schemable-validator && composer install --no-dev

# TypeScript client
npm install @schemable-validator/client
```

See [docs/01-installation.md](docs/01-installation.md) for full setup.

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

### 4. Client-side validation (TypeScript client)

```typescript
import { validateObject, isAllValid, extractErrors } from '@schemable-validator/client'

const schema = await fetch('/wp-json/schv/v1/schema/contact').then(r => r.json())

const result = validateObject(formData, schema)

if (!isAllValid(result)) {
  console.log(extractErrors(result))
}
```

Or with Zod:

```typescript
import { z } from 'zod'

// Build a Zod schema from the fetched JSON Schema, then extend with
// custom .superRefine() for x-unmapped-fields (see docs/06-custom-validation.md)
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
    ├─ toValidator()        → server-side validation (Respect/Validation)
    │
    └─ toJson()             → JSON Schema draft 2020-12
           │
           └─ REST endpoint (WordPress)
                  │
                  └─ @schemable-validator/client  → client-side validation
                     Zod / any JS validator
```

Constraints that cannot be expressed in JSON Schema (file uploads, custom rules) are recorded in `x-unmapped-fields` and delegated to the server automatically by the client library.

---

## 📖 Documentation

| | |
|:--|:--|
| [Installation](docs/01-installation.md) | Requirements, package structure |
| [Feature Guide](docs/02-feature-guide.md) | Validator, file validation, CSRF, reCAPTCHA |
| [Interfaces](docs/03-interfaces.md) | WordPress helpers, REST API |
| [Development](docs/04-development.md) | Local playground, E2E tests |
| [SchemaBuilder](docs/05-schema-builder.md) | `SV::object()` API, JSON Schema output samples |
| [Custom Validation](docs/06-custom-validation.md) | External libraries (libphonenumber etc.), `x-unmapped-fields` |

---

## 🔗 Dependencies

- [Respect/Validation](https://packagist.org/packages/respect/validation) ^2.2

---

## 📄 License

This project is open-sourced software licensed under the [MIT License](LICENSE).
