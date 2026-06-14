# Client

## Overview

`SchemaBuilder::toJsonSchema()` exports the PHP-side validation rules as a standard JSON Schema (Draft-07) object. Any JSON Schema-compatible validator can consume it directly on the client side.

`@uuki/schemable-validator-client` also ships built-in **adapters** that convert the JSON Schema into native Zod or Valibot schemas, enabling type inference and framework integration beyond what plain JSON Schema validators provide.

---

## Basic usage

Because the output is standard JSON Schema, you can validate with any conformant library. The example below uses [AJV](https://ajv.js.org/) — no adapter required.

```
pnpm add ajv ajv-formats
```

```ts
import Ajv from 'ajv'
import addFormats from 'ajv-formats'

const ajv = new Ajv()
addFormats(ajv)

// jsonSchema is the object returned by SchemaBuilder::toJsonSchema() on the PHP side
const jsonSchema = await fetch('/api/schema/contact').then(r => r.json())
const validate = ajv.compile(jsonSchema)

const formEl = document.querySelector<HTMLFormElement>('#my-form')!

formEl.addEventListener('submit', (e) => {
  e.preventDefault()

  const data = Object.fromEntries(new FormData(formEl))
  const valid = validate(data)

  if (valid) {
    console.log(data)
  } else {
    const errors: Record<string, string> = {}
    for (const err of validate.errors ?? []) {
      const field = (err.instancePath.slice(1) || err.params?.missingProperty) as string
      if (field) errors[field] = err.message ?? 'Invalid'
    }
    console.log(errors)
    // { email: 'must match format "email"' }
  }
})
```

---

## Adapter

When you need TypeScript type inference, reactive form integration, or custom refiners, use the built-in adapters. The recommended entry point is the `sv()` fluent builder.

The example below uses a minimal inline schema. In real usage `jsonSchema` comes from the server.

:::code-group

```ts [Zod]
import { sv } from '@uuki/schemable-validator-client/zod'

const jsonSchema = {
  $schema: 'http://json-schema.org/draft-07/schema#',
  type: 'object',
  properties: {
    name:  { type: 'string', minLength: 1 },
    email: { type: 'string', format: 'email' },
  },
  required: ['name', 'email'],
} as const

const schema = sv(jsonSchema).build()

const formEl = document.querySelector<HTMLFormElement>('#my-form')!

formEl.addEventListener('submit', (e) => {
  e.preventDefault()

  const data = Object.fromEntries(new FormData(formEl))
  const result = schema.safeParse(data)

  if (result.success) {
    console.log(result.data)
    // { name: 'Alice', email: 'alice@example.com' }
  } else {
    const errors: Record<string, string> = {}
    for (const issue of result.error.issues) {
      errors[String(issue.path[0])] = issue.message
    }
    console.log(errors)
    // { email: 'Invalid email' }
  }
})
```

```ts [Valibot]
import { sv } from '@uuki/schemable-validator-client/valibot'
import * as v from 'valibot'

const jsonSchema = {
  $schema: 'http://json-schema.org/draft-07/schema#',
  type: 'object',
  properties: {
    name:  { type: 'string', minLength: 1 },
    email: { type: 'string', format: 'email' },
  },
  required: ['name', 'email'],
} as const

const schema = sv(jsonSchema).build()

const formEl = document.querySelector<HTMLFormElement>('#my-form')!

formEl.addEventListener('submit', (e) => {
  e.preventDefault()

  const data = Object.fromEntries(new FormData(formEl))
  const result = v.safeParse(schema, data)

  if (result.success) {
    console.log(result.output)
    // { name: 'Alice', email: 'alice@example.com' }
  } else {
    const errors: Record<string, string> = {}
    for (const issue of result.issues) {
      errors[String(issue.path?.[0]?.key ?? '')] = issue.message
    }
    console.log(errors)
    // { email: 'Invalid email' }
  }
})
```

:::

---

## Custom mapping

The adapter converts most PHP rules automatically. Use these builder methods and options to handle the rest.

### Handling unsupported rules (`onUnknown`)

When the adapter encounters a field it cannot map, the `onUnknown` option controls what happens.

| Value | Behaviour |
|---|---|
| `'warn'` | `console.warn` and fall back to `unknown` |
| `'throw'` | throw an `Error` immediately |
| `(key, field) => Schema` | call the function and use the returned schema |
| _(default)_ | `'warn'` in development, `'throw'` in production |

The default resolves from `process.env.NODE_ENV`, which Vite and webpack replace with a string literal at build time — no extra configuration needed.

:::code-group

```ts [Zod]
import { sv } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

const schema = sv(jsonSchema)
  .onUnknown((key, field) => {
    if (field.format === 'hostname') {
      return z.string().regex(/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/)
    }
    throw new Error(`[zod] unsupported field "${key}": ${JSON.stringify(field)}`)
  })
  .build()
```

```ts [Valibot]
import { sv } from '@uuki/schemable-validator-client/valibot'
import * as v from 'valibot'

const schema = sv(jsonSchema)
  .onUnknown((key, field) => {
    if (field.format === 'hostname') {
      return v.pipe(v.string(), v.regex(/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/))
    }
    throw new Error(`[valibot] unsupported field "${key}": ${JSON.stringify(field)}`)
  })
  .build()
```

:::

### Conditional requirements (`when`)

`SchemaBuilder::when()` emits an `x-when` array. Call `.when()` on the builder to auto-apply every condition from the schema as a field-level required check:

:::code-group

```ts [Zod]
import { sv } from '@uuki/schemable-validator-client/zod'

// PHP: ->when('type', SV::equal('company'), ['company_name'])
const schema = sv(jsonSchema).when().build()

const result = schema.safeParse(formData)
```

```ts [Valibot]
import { sv } from '@uuki/schemable-validator-client/valibot'
import * as v from 'valibot'

const schema = sv(jsonSchema).when().build()

const result = v.safeParse(schema, formData)
```

:::

`.when()` reads every entry in `x-when` and enforces it with a field-level error. All operators (`===`, `!==`, `>=`, `<=`, `>`, `<`, field references) are supported.

### File fields (`x-unmapped-fields`)

`SV::file()` fields are excluded from the JSON Schema output by the PHP side and listed in `x-unmapped-fields`. The adapters skip them automatically. Add them back with `.extend()`:

:::code-group

```ts [Zod]
import { sv } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

const schema = sv(jsonSchema)
  .extend({ avatar: z.instanceof(File).refine((f) => f.size < 5_000_000, 'Max 5 MB') })
  .build()
```

```ts [Valibot]
import { sv } from '@uuki/schemable-validator-client/valibot'
import * as v from 'valibot'

const schema = sv(jsonSchema)
  .extend({ avatar: v.pipe(v.instance(File), v.maxSize(5_000_000)) })
  .build()
```

:::

### Cross-field constraints (`SV::respect`)

`SV::respect()` wraps arbitrary Respect/Validation rules that have no JSON Schema mapping. Implement their client-side equivalent as a pure function and inject it with `.refine()`:

:::code-group

```ts [Zod]
import { sv } from '@uuki/schemable-validator-client/zod'
import type { ZodRefiner } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

// PHP: 'confirm' => SV::respect(v::equals($data['password']))
const checkConfirm: ZodRefiner = (data, ctx) => {
  if (data.confirm !== data.password) {
    ctx.addIssue({
      code: 'custom',
      path: ['confirm'],
      message: 'Must match password',
    })
  }
}

const schema = sv(jsonSchema).refine(checkConfirm).build()
```

```ts [Valibot]
import { sv } from '@uuki/schemable-validator-client/valibot'
import type { ValibotRefiner } from '@uuki/schemable-validator-client/valibot'

const checkConfirm: ValibotRefiner = ({ dataset, addIssue }) => {
  if (!dataset.typed) return
  const d = dataset.value as { confirm?: string; password?: string }
  if (d.confirm !== d.password) addIssue({ message: 'Must match password' })
}

const schema = sv(jsonSchema).refine(checkConfirm).build()
```

:::

### Runtime coverage check

When the schema arrives via `fetch`, its contents are unknown until runtime. Use `checkZodSchema` / `checkValibotSchema` to get a coverage report without throwing:

```ts
import { checkZodSchema }     from '@uuki/schemable-validator-client/zod'
import { checkValibotSchema } from '@uuki/schemable-validator-client/valibot'

const jsonSchema = await fetchSchema('/api/schema/contact')

const report = checkZodSchema(jsonSchema)
// { supported: ['name', 'email'], unsupported: [{ key: 'host', reason: 'format "hostname" ...' }] }

if (report.unsupported.length) {
  console.warn('[schemable] unsupported fields:', report.unsupported)
}
```

Pass `check: true` to `createSv()` to run the check automatically during every `build()` call. Use `createSv()` to configure a factory once and share it across all forms:

```ts
import { createSv } from '@uuki/schemable-validator-client/zod'
import type { ZodRefiner } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

// Configure once at app startup — share across all forms
const sv = createSv({
  check: true,  // warn about unsupported fields during build()
  onUnknown: (key, field) => {
    // add per-format fallbacks here as they surface in dev
    throw new Error(`unsupported field "${key}": ${field.format ?? field.type}`)
  },
})

async function buildSchema(url: string) {
  const jsonSchema = await fetchSchema(url)
  return sv(jsonSchema).when().build()
}
```

---

## Advanced validation patterns

Some constraints cannot be expressed in a JSON Schema at all — they depend on real-time data, user state, or business rules that live entirely on the FE. These patterns compose with the builder.

The key design principle: **complex logic stays outside the builder**. Validator functions are plain functions injected with `.refine()` / `.refineAsync()`. No library coupling, no builder internals.

### Async field validation

Check a value against the server (e.g. username availability) by extending with an async schema field, or using `.refineAsync()` for cross-field async checks.

:::code-group

```ts [Zod]
import { sv } from '@uuki/schemable-validator-client/zod'
import type { ZodAsyncRefiner } from '@uuki/schemable-validator-client/zod'

const checkUsernameAvailable: ZodAsyncRefiner = async (data, ctx) => {
  const res = await fetch(`/api/users/check?name=${encodeURIComponent(data.username as string)}`)
  const { available } = await res.json() as { available: boolean }
  if (!available) {
    ctx.addIssue({ code: 'custom', path: ['username'], message: 'Username is already taken' })
  }
}

const jsonSchema = await fetchSchema('/api/schema/register')

// Async schemas require parseAsync
const schema = sv(jsonSchema).when().refineAsync(checkUsernameAvailable).build()
const result = await schema.safeParseAsync(formData)
```

```ts [Valibot]
import { sv } from '@uuki/schemable-validator-client/valibot'
import type { ValibotAsyncRefiner } from '@uuki/schemable-validator-client/valibot'
import * as v from 'valibot'

const checkUsernameAvailable: ValibotAsyncRefiner = async ({ dataset, addIssue }) => {
  if (!dataset.typed) return
  const d = dataset.value as { username: string }
  const res = await fetch(`/api/users/check?name=${encodeURIComponent(d.username)}`)
  const { available } = await res.json() as { available: boolean }
  if (!available) addIssue({ message: 'Username is already taken' })
}

const jsonSchema = await fetchSchema('/api/schema/register')

// Async schemas require safeParseAsync
const schema = sv(jsonSchema).when().refineAsync(checkUsernameAvailable).build()
const result = await v.safeParseAsync(schema, formData)
```

:::

### Cross-field business rules

Rules like date-range ordering ("end must be after start") cannot be expressed in JSON Schema. Add them with `.refine()` — the function is a pure validator with no builder dependency:

:::code-group

```ts [Zod]
import { sv } from '@uuki/schemable-validator-client/zod'
import type { ZodRefiner } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

const checkDateRange: ZodRefiner = (data, ctx) => {
  if (data.start_date && data.end_date && data.start_date >= data.end_date) {
    ctx.addIssue({
      code: 'custom',
      path: ['end_date'],
      message: 'Must be after start date',
    })
  }
}

const schema = sv(jsonSchema).when().refine(checkDateRange).build()
const result = schema.safeParse(formData)
```

```ts [Valibot]
import { sv } from '@uuki/schemable-validator-client/valibot'
import type { ValibotRefiner } from '@uuki/schemable-validator-client/valibot'

const checkDateRange: ValibotRefiner = ({ dataset, addIssue }) => {
  if (!dataset.typed) return
  const d = dataset.value as { start_date?: string; end_date?: string }
  if (d.start_date && d.end_date && d.start_date >= d.end_date) {
    addIssue({ message: 'Must be after start date' })
  }
}

const schema = sv(jsonSchema).when().refine(checkDateRange).build()
const result = v.safeParse(schema, formData)
```

:::

### Composing all layers

A complete fetch-based form layering adapter conversion, `x-when` conditions, file field extension, and a custom business rule:

```ts
import { createSv } from '@uuki/schemable-validator-client/zod'
import type { ZodRefiner } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

// Shared factory — configured once
const sv = createSv({
  check: true,
  onUnknown: (key, field) => {
    if (field.format === 'hostname') {
      return z.string().regex(/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/)
    }
    throw new Error(`unsupported field "${key}"`)
  },
})

// External validator — pure function, no builder coupling
const checkDeliveryDate: ZodRefiner = (data, ctx) => {
  if (data.delivery_date && (data.delivery_date as string) < new Date().toISOString().slice(0, 10)) {
    ctx.addIssue({
      code: 'custom',
      path: ['delivery_date'],
      message: 'Delivery date must be today or later',
    })
  }
}

async function buildOrderSchema() {
  const jsonSchema = await fetchSchema('/api/schema/order')

  return sv(jsonSchema)
    .extend({ receipt: z.instanceof(File).optional() })  // file field from x-unmapped-fields
    .when()                                               // x-when conditional requirements
    .refine(checkDeliveryDate)                            // FE business rule
    .build()
}
```

### Custom constraints

`validateObject` evaluates each field through a **Constraint pipeline** — a chain of pure functions typed as `(state: FieldState) => FieldState`. Each function appends to `state.errors` on failure and passes the state through unchanged on success. All errors are accumulated; there is no short-circuit.

Define a custom `Constraint` and compose it alongside the built-in rules with `composeConstraints`:

```ts
import {
  composeConstraints, constraintsFromSchema, validateObject,
} from '@uuki/schemable-validator-client'
import type { Constraint, ObjectSchema } from '@uuki/schemable-validator-client'

// A Constraint is a pure function: FieldState → FieldState
const checkJapanesePhone: Constraint = (state) =>
  /^(0\d{9,10}|0\d{1,4}-\d{1,4}-\d{3,4})$/.test(state.value)
    ? state
    : { ...state, errors: [...state.errors, '日本の電話番号形式で入力してください'] }

// Compose: built-in string type check → custom phone format check
const phoneConstraint = composeConstraints([
  constraintsFromSchema({ type: 'string' }),
  checkJapanesePhone,
])
```

Apply it on top of `validateObject` output by merging the field result:

```ts
function validateWithCustomRules(data: Record<string, string>) {
  const base = validateObject(data, schema)
  const phoneState = phoneConstraint({ value: data.phone ?? '', errors: [] })
  return {
    ...base,
    phone: {
      value: data.phone ?? '',
      is_valid: base.phone.is_valid && phoneState.errors.length === 0,
      errors:
        [...(base.phone?.errors ?? []), ...phoneState.errors].length > 0
          ? [...(base.phone?.errors ?? []), ...phoneState.errors]
          : null,
    },
  }
}
```

→ Full implementation sample: [Examples — 4. Adding a Custom Constraint](/examples/client#4-adding-a-custom-constraint)

---

## Rule coverage

Coverage is measured against the 19 mappable rules in `RuleMapper.php`.  
(`SV::file()` / `SV::respect()` are excluded by the PHP side via `x-unmapped-fields` and are never passed to the adapter.)

| PHP rule | JSON Schema field | Zod | Valibot |
|---|---|:---:|:---:|
| `string` | `type: 'string'` | ✅ | ✅ |
| `integer` | `type: 'integer'` | ✅ | ✅ |
| `number` | `type: 'number'` | ✅ | ✅ |
| `boolean` | `type: 'boolean'` | ✅ | ✅ |
| `.nullable()` | `type: ['X', 'null']` | ✅ | ✅ |
| `length` | `minLength` / `maxLength` | ✅ | ✅ |
| `min` / `max` | `minimum` / `maximum` | ✅ | ✅ |
| `email` | `format: 'email'` | ✅ | ✅ |
| `url` | `format: 'uri'` | ✅ | ✅ |
| `date` | `format: 'date'` | ✅ | ✅ |
| `dateTime` | `format: 'date-time'` | ✅ | ✅ |
| `time` | `format: 'time'` | ✅ | ✅ |
| `uuid` | `format: 'uuid'` | ✅ | ✅ |
| `ipv4` | `format: 'ipv4'` | ✅ | ✅ |
| `ipv6` | `format: 'ipv6'` | ✅ | ✅ |
| `pattern` | `pattern` | ✅ | ✅ |
| `slug` | `pattern: '^[a-z0-9]+...'` | ✅ | ✅ |
| `in` | `enum` | ✅ | ✅ |
| `ArraySchema` | `type: 'array'` + `items` / `minItems` / `maxItems` | ✅ | ✅ |
| `domain` | `format: 'hostname'` | ⚠️ | ⚠️ |

**18 / 19 — 94.7%** for both adapters.

`hostname` has no built-in equivalent in Zod or Valibot. Use `onUnknown` to supply a custom mapping (see above).
