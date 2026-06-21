# Custom Validation

---

## Overview

This plugin is designed to centrally define **structural constraints** — such as field types, formats, and character limits — on the PHP side, and share them with the client via JSON Schema.

That said, real-world forms sometimes require "principled validation" that is locale- or environment-specific (e.g., verifying that a phone number belongs to a valid numbering plan). Because such constraints are difficult to express in JSON Schema, the plugin provides **`SV::custom(callable)`** as the primary dependency-free escape hatch for injecting arbitrary validation logic. For projects that already use the Respect/Validation library, `RespectRules::rule()` is also available as an optional alternative.

---

## What Is "Principled Validation"?

JSON Schema (draft 2020-12) is a specification for describing the **structure and format** of fields; it cannot express every validation rule.

For example, the following constraints cannot be represented with keywords like type, length, or pattern:

- Whether a phone number belongs to a real numbering plan (per-country number plan validation)
- Whether a credit card number satisfies the Luhn algorithm
- Whether an IBAN is consistent with its country code
- Whether a password has sufficient strength

These are not "string format checks" but rather **validations based on domain-specific rules or external databases**. While a regular expression or a JSON Schema keyword can approximate them, a complete representation is fundamentally impossible.

In this plugin, such constraints are wrapped with `SV::custom()` or `RespectRules::rule()` and recorded in the `x-unmapped-fields` extension of the JSON Schema output.

```text
SV::custom($predicate)                       [PRIMARY - dependency-free]
  │
  ├─ Server side: validated with the callable predicate
  │
  └─ JSON Schema: recorded in x-unmapped-fields (not included in properties)
       │
       └─ Client side: add custom validation via @uuki/schemable-validator-client / Zod

RespectRules::rule($rule)                    [requires Respect/Validation]
  │
  ├─ Server side: validated with Respect/Validation
  │
  └─ JSON Schema: recorded in x-unmapped-fields (not included in properties)
       │
       └─ Client side: add custom validation via @uuki/schemable-validator-client / Zod
```

---

## Integration Patterns with External Libraries

When implementing constraints that cannot be expressed in JSON Schema, choose appropriate libraries for both the backend and frontend, and connect them through the plugin's escape hatches.

### PHP Side (Server)

**Primary: `SV::custom(callable, message)`** (dependency-free)

`SV::custom()` accepts a callable predicate that returns `bool`. No external dependencies are required.

```php
SV::custom(
  fn(mixed $value): bool => someExternalLibrary::validate($value),
  'Validation failed'
)
```

**Alternative: `RespectRules::rule(rule)`** (requires `respect/validation`)

`RespectRules::rule()` accepts a Respect/Validation `Validator` instance. Using `v::callback()`, you can inject any logic or external library.

```php
use Respect\Validation\Validator as v;

RespectRules::rule(
  v::callback(function (mixed $value): bool {
    // Write any validation logic here
    return someExternalLibrary::validate($value);
  })
)
```

The server is always the authoritative validator. Client-side validation is treated purely as a UX aid.

### JS Side (Client)

Fields listed in `x-unmapped-fields` are automatically skipped by `validateObject`. If you need client-side validation for them, add it via **`Constraint` from `@uuki/schemable-validator-client`** or **Zod's `.superRefine()`**.

**Using `@uuki/schemable-validator-client`:**

```typescript
import { type Constraint } from '@uuki/schemable-validator-client'

const checkCustomField: Constraint = (state) => {
  if (state.value === '') return state // pass through empty input for optional fields
  const ok = someJsLibrary.validate(state.value)
  return ok ? state : { ...state, errors: [...state.errors, 'Error message'] }
}
```

**Using Zod:**

```typescript
const schema = buildZodSchema(jsonSchema).extend({
  fieldName: z.string().optional().superRefine((val, ctx) => {
    if (!val) return
    if (!someJsLibrary.validate(val)) {
      ctx.addIssue({ code: 'custom', message: 'Error message' })
    }
  }),
})
```

### Applicable Use Cases

| Use case | PHP library | JS library | JSON Schema |
|:--|:--|:--|:--|
| Phone number (E.164 / per-country) | `giggsey/libphonenumber-for-php` | `libphonenumber-js` | UNMAPPABLE |
| IBAN / bank account number | `globalcitizen/php-iban` | `ibantools` | UNMAPPABLE |
| Credit card (Luhn) | `RespectRules::creditCard()` | Custom Luhn implementation | UNMAPPABLE |
| Postal code (per-country) | `RespectRules::postalCode()` | `postal-codes-js` | Approximable with `pattern` |
| Password strength | Custom callback | `zxcvbn` | UNMAPPABLE |

---

## Use Case: Phone Number Validation

Phone numbers are a canonical example where regex approximations fall short. `libphonenumber` (by Google) maintains a database of per-country numbering plans based on ITU-T E.164 and can accurately validate real number ranges.

### PHP Side - `giggsey/libphonenumber-for-php`

#### Installation

```bash
composer require giggsey/libphonenumber-for-php
```

#### Primary: Using SV::custom()

```php
use SchemableValidator\SV;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;

/**
 * A libphonenumber-based phone number validator with a region code.
 * When $region = null, E.164 format (+81...) is required.
 */
function makePhonePredicate(string $region = null): callable {
  $util = PhoneNumberUtil::getInstance();

  return function (mixed $value) use ($util, $region): bool {
    if (!is_string($value) || $value === '') {
      return false;
    }
    try {
      $number = $util->parse($value, $region);
      return $region !== null
        ? $util->isValidNumberForRegion($number, $region)
        : $util->isValidNumber($number);
    } catch (NumberParseException) {
      return false;
    }
  };
}
```

#### Integrating into SchemaBuilder (SV::custom)

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name'  => SV::string()->min(1)->max(100),
  'email' => SV::string()->email(),
  'tel'   => SV::custom(makePhonePredicate('JP'), 'Please enter a valid phone number')->optional(),
]);
```

#### Alternative: Using RespectRules::rule()

```php
use Respect\Validation\Validator as v;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;

function makePhoneRule(string $region = null): \Respect\Validation\Validator {
  $util = PhoneNumberUtil::getInstance();

  return v::callback(function (mixed $value) use ($util, $region): bool {
    if (!is_string($value) || $value === '') {
      return false;
    }
    try {
      $number = $util->parse($value, $region);
      return $region !== null
        ? $util->isValidNumberForRegion($number, $region)
        : $util->isValidNumber($number);
    } catch (NumberParseException) {
      return false;
    }
  });
}

$schema = SV::object([
  'name'  => SV::string()->min(1)->max(100),
  'email' => SV::string()->email(),
  'tel'   => RespectRules::rule(makePhoneRule('JP'))->optional(),
]);
```

#### JSON Schema Output

When `toJson()` is called, `tel` appears in `x-unmapped-fields` (not in `properties`).

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "properties": {
    "name":  { "type": "string", "minLength": 1, "maxLength": 100 },
    "email": { "type": "string", "format": "email" }
  },
  "required": ["name", "email"],
  "x-unmapped-fields": ["tel"]
}
```

---

### JS Side (`@uuki/schemable-validator-client`) - `libphonenumber-js`

The `Constraint` in `@uuki/schemable-validator-client` is a pure function of `FieldState → FieldState`. You can wrap an external library directly.

#### Installation

```bash
npm install libphonenumber-js
```

#### Implementing a Custom Constraint

```typescript
import { isValidPhoneNumber } from 'libphonenumber-js'
import { type Constraint } from '@uuki/schemable-validator-client'

export const checkJapanesePhone: Constraint = (state) => {
  if (state.value === '') return state // pass through empty input for optional fields

  return isValidPhoneNumber(state.value, 'JP')
    ? state
    : { ...state, errors: [...state.errors, 'Please enter a valid Japanese phone number'] }
}
```

#### Composing with `validateObject`

```typescript
import { validateObject } from '@uuki/schemable-validator-client'
import { checkJapanesePhone } from './constraints/phone'

async function validate(data: Record<string, string>, jsonSchema: ObjectSchema) {
  const result = { ...validateObject(data, jsonSchema) }

  // Additional validation for fields in x-unmapped-fields
  if ((jsonSchema['x-unmapped-fields'] ?? []).includes('tel')) {
    const state = checkJapanesePhone({ value: data['tel'] ?? '', errors: [] })
    result['tel'] = {
      value:    state.value,
      is_valid: state.errors.length === 0,
      errors:   state.errors.length > 0 ? state.errors : null,
    }
  }

  return result
}
```

---

### JS Side (Zod) - `libphonenumber-js`

```typescript
import { z } from 'zod'
import { isValidPhoneNumber } from 'libphonenumber-js'

const contactSchema = buildZodSchema(jsonSchema).extend({
  tel: z.string().optional().superRefine((val, ctx) => {
    if (!val) return
    if (!isValidPhoneNumber(val, 'JP')) {
      ctx.addIssue({
        code: 'custom',
        message: 'Please enter a valid Japanese phone number',
      })
    }
  }),
})
```
