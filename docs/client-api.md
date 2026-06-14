# Client API Reference

`@uuki/schemable-validator-client` exposes four distinct groups of exports.

| Import path | What you get |
|---|---|
| `@uuki/schemable-validator-client` | `validateObject`, Result primitives, Constraint pipeline, schema types |
| `@uuki/schemable-validator-client/zod` | `toZodSchema`, `checkZodSchema` |
| `@uuki/schemable-validator-client/valibot` | `toValibotSchema`, `checkValibotSchema` |

---

## Core validator

### `validateObject(data, schema)`

Validates a flat key/value record against an `ObjectSchema`. Returns a `ValidationResult` keyed by field name.

```ts
import { validateObject } from '@uuki/schemable-validator-client'
import type { ObjectSchema } from '@uuki/schemable-validator-client'

const schema: ObjectSchema = await fetch('/api/schema/contact').then(r => r.json())

const result = validateObject(
  { name: 'Alice', email: 'bad-email' },
  schema,
)
// { name: { value: 'Alice', is_valid: true, errors: null },
//   email: { value: 'bad-email', is_valid: false, errors: ['must be a valid email'] } }
```

**Parameters**

| | Type | Description |
|---|---|---|
| `data` | `Record<string, string \| readonly string[]>` | Form values. Array fields (checkboxes, multi-selects) pass `string[]`. |
| `schema` | `ObjectSchema` | JSON Schema from `SchemaBuilder::toJsonSchema()`. |

**Returns** `ValidationResult` — `Record<string, FieldResult>`

**Behaviour notes**
- Empty optional fields are always valid.
- `x-unmapped-fields` (SV::file, SV::respect) are passed through as `is_valid: true`.
- `x-when` conditional requirements are evaluated automatically.
- Falls back to `if/then` / `allOf` for schemas without `x-when`.

---

### `isAllValid(result)`

Returns `true` when every field in a `ValidationResult` is valid.

```ts
import { validateObject, isAllValid } from '@uuki/schemable-validator-client'

const result = validateObject(formData, schema)
if (isAllValid(result)) {
  await submitForm(formData)
}
```

---

### `extractErrors(result)`

Returns only the invalid fields, stripping the `value` and `is_valid` keys. Useful for rendering error messages.

```ts
import { validateObject, extractErrors } from '@uuki/schemable-validator-client'

const errors = extractErrors(validateObject(formData, schema))
// { email: ['must be a valid email'], age: ['must be at least 18'] }

for (const [field, messages] of Object.entries(errors)) {
  showError(field, messages.join(', '))
}
```

**Returns** `Record<string, readonly string[]>` — only the failing fields.

---

## Result primitives

Railway Oriented Programming (ROP) helpers. Every `Result<A, E>` is either `Ok<A>` or `Err<E>` — no exceptions, no `null`.

### `ok(value)` / `err(error)`

Construct the two variants.

```ts
import { ok, err } from '@uuki/schemable-validator-client'

const success = ok('validated value')   // Ok<string>
const failure = err(['is required'])    // Err<string[]>
```

---

### `isOk(result)` / `isErr(result)`

Type-narrowing guards.

```ts
import { isOk, isErr } from '@uuki/schemable-validator-client'

if (isOk(result)) {
  console.log(result.value)   // narrowed to Ok<A>
}
if (isErr(result)) {
  console.log(result.error)   // narrowed to Err<E>
}
```

---

### `map(result, fn)`

Transform the `Ok` value; pass `Err` through unchanged.

```ts
import { ok, err, map } from '@uuki/schemable-validator-client'

const r = ok('  hello  ')
const trimmed = map(r, (s) => s.trim())   // Ok<'hello'>
const failed  = map(err('oops'), (s) => s.trim())  // Err<'oops'>
```

---

### `flatMap(result, fn)`

Chain an `Ok` value into another `Result`-returning function. Short-circuits on `Err`.

```ts
import { ok, err, flatMap } from '@uuki/schemable-validator-client'

const parseAge = (s: string) =>
  Number.isInteger(+s) ? ok(+s) : err('not a number')

const checkAdult = (n: number) =>
  n >= 18 ? ok(n) : err('must be at least 18')

const result = flatMap(parseAge('21'), checkAdult)  // Ok<21>
const failed = flatMap(parseAge('abc'), checkAdult) // Err<'not a number'>
```

---

### `mapErr(result, fn)`

Transform the `Err` value; pass `Ok` through unchanged. Useful for translating error messages.

```ts
import { err, mapErr } from '@uuki/schemable-validator-client'

const translated = mapErr(
  err('is required'),
  (msg) => `このフィールドは${msg}`,
)
// Err<'このフィールドはis required'>
```

---

### `getOrElse(result, fallback)`

Unwrap the `Ok` value, or return a fallback for `Err`.

```ts
import { ok, err, getOrElse } from '@uuki/schemable-validator-client'

getOrElse(ok('Alice'), 'unknown')  // 'Alice'
getOrElse(err('missing'), 'unknown')  // 'unknown'
```

---

## Constraint pipeline

Low-level building blocks used internally by `validateObject`. Export them when you need custom field validation logic without the full validator.

### `constraintsFromSchema(schema)`

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

### `composeConstraints(constraints)`

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

### Individual constraint factories

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

### `PATTERN_MAX_INPUT_LENGTH`

```ts
export const PATTERN_MAX_INPUT_LENGTH = 500
```

The default maximum input length before `checkPattern` skips client-side regex evaluation. Override per-call:

```ts
import { checkPattern } from '@uuki/schemable-validator-client'

// Always evaluate, regardless of length
const strictSlug = checkPattern('^[a-z0-9-]+$', Infinity)
```

---

## Zod adapter

```ts
import { toZodSchema, checkZodSchema } from '@uuki/schemable-validator-client/zod'
```

Requires `zod` as a peer dependency (`pnpm add zod`).

### `toZodSchema(jsonSchema, options?)`

Convert an `ObjectSchema` to a Zod v4 `ZodObject`.

```ts
import { toZodSchema } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

const schema = toZodSchema(jsonSchema)
const result = schema.safeParse(formData)
```

**Options**

| Option | Type | Default | Description |
|---|---|---|---|
| `onUnknown` | `'warn' \| 'throw' \| (key, field) => ZodTypeAny` | `process.env.NODE_ENV === 'production' ? 'throw' : 'warn'` | Behaviour when a field has no Zod equivalent. |

**`onUnknown` values**

| Value | Behaviour |
|---|---|
| `'warn'` | `console.warn` and fall back to `z.unknown()` |
| `'throw'` | Throw an `Error` immediately |
| `(key, field) => schema` | Return a custom schema for that field |

```ts
// Custom mapping for the one unsupported format
const schema = toZodSchema(jsonSchema, {
  onUnknown: (key, field) => {
    if (field.format === 'hostname') {
      return z.string().regex(/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/)
    }
    throw new Error(`unsupported field "${key}"`)
  },
})
```

**Limitations**
- `x-unmapped-fields` (SV::file, SV::respect) are skipped. Add them with `.extend()`.
- `format: 'hostname'` has no Zod built-in — use `onUnknown`.
- `x-when` / `if/then` conditionals are not mapped — add via `.superRefine()`.

---

### `checkZodSchema(jsonSchema)`

Dry-run: report which fields are and are not mappable, without building the schema or throwing.

```ts
import { checkZodSchema } from '@uuki/schemable-validator-client/zod'

const { supported, unsupported } = checkZodSchema(jsonSchema)
// supported:   ['name', 'email', 'age']
// unsupported: [{ key: 'host', field: {...}, reason: 'format "hostname" has no built-in Zod equivalent' }]

if (unsupported.length) {
  console.warn('[schemable] fields needing onUnknown:', unsupported)
}
```

**Returns** `ZodSchemaReport`

```ts
interface ZodSchemaReport {
  supported:   string[]
  unsupported: { key: string; field: PropertySchema; reason: string }[]
}
```

---

## Valibot adapter

```ts
import { toValibotSchema, checkValibotSchema } from '@uuki/schemable-validator-client/valibot'
```

Requires `valibot` as a peer dependency (`pnpm add valibot`).

### `toValibotSchema(jsonSchema, options?)`

Convert an `ObjectSchema` to a Valibot v1 `ObjectSchema`.

```ts
import { toValibotSchema } from '@uuki/schemable-validator-client/valibot'
import * as v from 'valibot'

const schema = toValibotSchema(jsonSchema)
const result = v.safeParse(schema, formData)
```

**Options**

| Option | Type | Default | Description |
|---|---|---|---|
| `onUnknown` | `'warn' \| 'throw' \| (key, field) => GenericSchema` | `process.env.NODE_ENV === 'production' ? 'throw' : 'warn'` | Behaviour when a field has no Valibot equivalent. |

```ts
import * as v from 'valibot'

const schema = toValibotSchema(jsonSchema, {
  onUnknown: (key, field) => {
    if (field.format === 'hostname') {
      return v.pipe(v.string(), v.regex(/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/))
    }
    throw new Error(`unsupported field "${key}"`)
  },
})
```

**Limitations**
- `x-unmapped-fields` are skipped. Add them by spreading `base.entries` into a new `v.object({ ...base.entries, myField: ... })`.
- `format: 'hostname'` has no Valibot built-in — use `onUnknown`.
- `x-when` / `if/then` conditionals are not mapped — add via `v.rawCheck`.

---

### `checkValibotSchema(jsonSchema)`

Dry-run equivalent of `checkZodSchema` for Valibot.

```ts
import { checkValibotSchema } from '@uuki/schemable-validator-client/valibot'

const { supported, unsupported } = checkValibotSchema(jsonSchema)
```

**Returns** `ValibotSchemaReport`

```ts
interface ValibotSchemaReport {
  supported:   string[]
  unsupported: { key: string; field: PropertySchema; reason: string }[]
}
```

---

## Type reference

All types are re-exported from the main entry.

```ts
import type {
  // Result
  Ok, Err, Result,
  // Constraint pipeline
  FieldState, Constraint,
  // Validator
  FieldResult, ValidationResult,
  // Schema
  JsonSchemaType, PropertySchema, ObjectSchema,
  ConditionalSchema, WhenCondition, WhenOp,
} from '@uuki/schemable-validator-client'
```

### `ObjectSchema`

Top-level JSON Schema from `SchemaBuilder::toJsonSchema()`.

```ts
type ObjectSchema = {
  $schema: string
  type: 'object'
  properties: Record<string, PropertySchema>
  required?: readonly string[]
  'x-unmapped-fields'?: readonly string[]   // SV::file / SV::respect fields
  'x-when'?: readonly WhenCondition[]       // SchemaBuilder::when() conditions
  if?: ConditionalSchema['if']
  then?: ConditionalSchema['then']
  allOf?: readonly ConditionalSchema[]
}
```

### `PropertySchema`

Per-field fragment inside `properties`.

```ts
type PropertySchema = {
  type?:      JsonSchemaType | readonly JsonSchemaType[]
  minLength?: number
  maxLength?: number
  format?:    'email' | 'uri' | 'date' | 'date-time' | 'time' | 'uuid' | 'ipv4' | 'ipv6' | 'hostname'
  pattern?:   string
  enum?:      readonly string[]
  minimum?:   number
  maximum?:   number
  items?:     PropertySchema   // array element schema
  minItems?:  number
  maxItems?:  number
}
```

### `WhenCondition`

One entry in the `x-when` array.

```ts
type WhenOp = '===' | '!==' | '>=' | '<=' | '>' | '<'

type WhenCondition =
  | { field: string; op: WhenOp; equals: unknown;      require: readonly string[] }
  | { field: string; op: WhenOp; equalsField: string;  require: readonly string[] }
```

### `FieldResult` / `ValidationResult`

Output of `validateObject`. Mirrors the PHP `Validator::getResult()` shape for cross-stack consistency.

```ts
type FieldResult = {
  value:    string | readonly string[]
  is_valid: boolean
  errors:   readonly string[] | null  // null when is_valid is true
}

type ValidationResult = Record<string, FieldResult>
```

### `FieldState` / `Constraint`

Primitives for the constraint pipeline.

```ts
type FieldState = {
  value:  string
  errors: readonly string[]
}

type Constraint = (state: FieldState) => FieldState
```
