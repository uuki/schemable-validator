# Constraint Pipeline

Low-level building blocks used internally by `validateObject`. Export them when you need custom field validation logic without the full validator.

```ts
import {
  constraintsFromSchema, composeConstraints, applyTransform,
  checkType, checkMinLength, checkMaxLength,
  checkMinimum, checkMaximum, checkFormat,
  checkPattern, checkEnum,
  PATTERN_MAX_INPUT_LENGTH, DEFAULT_MESSAGES,
} from '@uuki/schemable-validator-client'
```

---

## `constraintsFromSchema(schema)`

Build a single composed `Constraint` from a `PropertySchema`. This is the same function `validateObject` calls internally.

```ts
import { constraintsFromSchema } from '@uuki/schemable-validator-client'
import type { PropertySchema } from '@uuki/schemable-validator-client'

const schema: PropertySchema = { type: 'string', format: 'email', minLength: 5 }
const validate = constraintsFromSchema(schema)

const result = validate({ value: 'bad', errors: [] })
// { value: 'bad', errors: ['must be a valid email'] }
```

---

## `composeConstraints(constraints)`

Combine an array of `Constraint` functions into one. All constraints run in order, accumulating every error (no short-circuit).

```ts
import { composeConstraints, checkMinLength, checkFormat } from '@uuki/schemable-validator-client'

const validate = composeConstraints([
  checkMinLength(3),
  checkFormat('email'),
])

validate({ value: 'x', errors: [] })
// { value: 'x', errors: [
//     'must be at least 3 characters long',
//     'must be a valid email'
//   ] }
```

---

## `applyTransform(value, transforms)`

Apply the `x-transform` catalog transforms to a string value before validation. Returns the transformed string.

Supported transforms: `trim`, `toLowerCase`, `toUpperCase`.

```ts
import { applyTransform } from '@uuki/schemable-validator-client'

applyTransform('  Hello World  ', ['trim', 'toLowerCase'])
// 'hello world'
```

---

## Individual constraint factories

Each factory returns a `Constraint` -- `(state: FieldState) => FieldState`. All factories accept an optional trailing `message?` parameter to override the default error text from `DEFAULT_MESSAGES`.

| Function | Description |
|---|---|
| `checkType(type, message?)` | Validates `integer` / `number` / `boolean` coercion. Strings are always accepted. |
| `checkMinLength(min, message?)` | String length >= min. |
| `checkMaxLength(max, message?)` | String length <= max. |
| `checkMinimum(min, message?)` | Numeric value >= min. |
| `checkMaximum(max, message?)` | Numeric value <= max. |
| `checkFormat(format, message?)` | Matches one of the built-in format regexes (see table below). |
| `checkPattern(pattern, maxLen?, message?)` | Tests against a user-supplied regex string. Inputs longer than `maxLen` (default `500`) are skipped to prevent ReDoS. |
| `checkEnum(values, message?)` | Value must be in the given list. |

**Built-in formats for `checkFormat`**

| Format | Pattern |
|---|---|
| `email` | `local@domain.tld` -- rejects control chars and zero-width Unicode |
| `uri` | `https?://...` -- rejects control chars |
| `date` | `YYYY-MM-DD` |
| `date-time` | `YYYY-MM-DDTHH:MM:SS[.ms](Z\|+-HH:MM)` |
| `time` | `HH:MM:SS[.ms][Z\|+-HH:MM]` |
| `uuid` | RFC 4122 |
| `ipv4` | dotted-decimal |
| `ipv6` | full / compressed |
| `hostname` | `label.label.tld` |

```ts
import { checkFormat, checkMinLength, composeConstraints } from '@uuki/schemable-validator-client'

const emailField = composeConstraints([checkMinLength(1), checkFormat('email')])

emailField({ value: '', errors: [] })
// { value: '', errors: ['must be at least 1 character long'] }

emailField({ value: 'not-an-email', errors: [] })
// { value: 'not-an-email', errors: ['must be a valid email'] }
```

---

## `DEFAULT_MESSAGES` and `substituteVars`

`DEFAULT_MESSAGES` is a frozen catalog of default error strings keyed by rule name (`required`, `minLength`, `email`, etc.). Override individual messages via the `message?` parameter on each constraint factory, or via the `errorMessage` property on `PropertySchema`.

Message templates use `{var}` placeholders (an ICU subset) resolved by `substituteVars`. For example, `'must be at least {min} character{plural} long'` is interpolated at runtime with the constraint's actual values.

---

## `PATTERN_MAX_INPUT_LENGTH`

```ts
export const PATTERN_MAX_INPUT_LENGTH = 500
```

The default maximum input length before `checkPattern` skips client-side regex evaluation. Override per-call:

```ts
import { checkPattern } from '@uuki/schemable-validator-client'

// Always evaluate, regardless of length
const strictSlug = checkPattern('^[a-z0-9-]+$', Infinity)
```
