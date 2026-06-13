# Schemable Validator

Define validation constraints once in PHP — export to [JSON Schema draft 2020-12](https://json-schema.org/) and consume from any JavaScript framework.

The single PHP schema drives server-side validation, a REST endpoint that delivers the constraint definition, and a framework-agnostic TypeScript SDK for client-side validation. No duplicate rule definitions across the stack.

---

## How it works

```
SV::object([...])          ← PHP: single source of truth
    │
    ├─ toValidator()        → server-side validation (Respect/Validation)
    │
    └─ toJson()             → JSON Schema draft 2020-12
           │
           └─ REST endpoint (WordPress)
                  │
                  └─ @schemable-validator/sdk  → client-side validation
                     Zod / any JS validator
```

Constraints that cannot be expressed in JSON Schema (file uploads, custom rules) are recorded in `x-unmapped-fields` and delegated to the server automatically by the SDK.

---

## Packages

| Package | Description |
|:--|:--|
| `uuki/schemable-validator` | PHP core library (framework-agnostic) |
| `wp-schemable-validator` | WordPress plugin — REST endpoint, helpers, admin UI |
| `@schemable-validator/sdk` | TypeScript SDK — validates against JSON Schema output |

---

## Quick Start

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

### 4. Client-side validation (TypeScript SDK)

```typescript
import { validateObject, isAllValid, extractErrors } from '@schemable-validator/sdk'

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

## JSON Schema output

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

## Installation

```shell
# PHP core
composer require uuki/schemable-validator:0.x@dev

# WordPress plugin
cd packages/wp-schemable-validator && composer install --no-dev

# TypeScript SDK
npm install @schemable-validator/sdk
```

See [docs/01-installation.md](docs/01-installation.md) for full setup.

---

## Documentation

| | |
|:--|:--|
| [Installation](docs/01-installation.md) | Requirements, package structure |
| [Feature Guide](docs/02-feature-guide.md) | Validator, file validation, CSRF, reCAPTCHA |
| [Interfaces](docs/03-interfaces.md) | WordPress helpers, REST API |
| [Development](docs/04-development.md) | Local playground, E2E tests |
| [SchemaBuilder](docs/05-schema-builder.md) | `SV::object()` API, JSON Schema output samples |
| [Custom Validation](docs/06-custom-validation.md) | External libraries (libphonenumber etc.), `x-unmapped-fields` |

---

## Dependencies

- [Respect/Validation](https://packagist.org/packages/respect/validation) ^2.2
