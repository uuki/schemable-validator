# Constraint Pipeline

Low-level building blocks used internally by `validateObject`. Export them when you need custom field validation logic without the full validator.

```ts
import {
  constraintsFromSchema, composeConstraints,
  checkType, checkMinLength, checkMaxLength,
  checkMinimum, checkMaximum, checkFormat,
  checkPattern, checkEnum,
  PATTERN_MAX_INPUT_LENGTH,
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

## Individual constraint factories

Each factory returns a `Constraint` — `(state: FieldState) => FieldState`.

| Function | Description |
|---|---|
| `checkType(type)` | Validates `integer` / `number` / `boolean` coercion. Strings are always accepted. |
| `checkMinLength(min)` | String length ≥ min. |
| `checkMaxLength(max)` | String length ≤ max. |
| `checkMinimum(min)` | Numeric value ≥ min. |
| `checkMaximum(max)` | Numeric value ≤ max. |
| `checkFormat(format)` | Matches one of the built-in format regexes (see table below). |
| `checkPattern(pattern, maxLen?)` | Tests against a user-supplied regex string. Inputs longer than `maxLen` (default `500`) are skipped to prevent ReDoS. |
| `checkEnum(values)` | Value must be in the given list. |

**Built-in formats for `checkFormat`**

| Format | Pattern |
|---|---|
| `email` | `local@domain.tld` — rejects control chars and zero-width Unicode |
| `uri` | `https?://…` — rejects control chars |
| `date` | `YYYY-MM-DD` |
| `date-time` | `YYYY-MM-DDTHH:MM:SS[.ms](Z\|±HH:MM)` |
| `time` | `HH:MM:SS[.ms][Z\|±HH:MM]` |
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
