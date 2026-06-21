# Zod Adapter

```ts
import { sv, createSv, toZodSchema, checkZodSchema } from '@uuki/schemable-validator-client/zod'
import type { ZodRefiner, ZodAsyncRefiner, SvConfig } from '@uuki/schemable-validator-client/zod'
```

Requires `zod` as a peer dependency (`pnpm add zod`).

---

## `sv(jsonSchema)`

Fluent schema builder. Equivalent to `createSv()(jsonSchema)`.

```ts
import { sv } from '@uuki/schemable-validator-client/zod'

const schema = sv(jsonSchema)
  .onUnknown(myFallback)    // optional: override onUnknown for this schema
  .extend({ avatar: z.instanceof(File) })  // add fields absent from JSON Schema
  .when()                   // auto-apply x-when conditions from the schema
  .refine(checkDates)       // inject sync cross-field validator
  .refineAsync(checkName)   // inject async validator (requires parseAsync)
  .build()                  // produce the final Zod schema
```

**Method call order does not matter.** `build()` always applies phases in the correct order: adapter → extend → when/refiners.

**Builder methods**

| Method | Description |
|---|---|
| `.onUnknown(policy)` | Override `onUnknown` for this schema only. |
| `.extend(fields)` | Add or override fields absent from the JSON Schema (e.g. `SV::file` uploads). |
| `.when()` | Auto-apply all `x-when` conditional requirements from the schema. |
| `.refine(fn)` | Inject a synchronous validator. Logic lives in `fn`, outside the builder. |
| `.refineAsync(fn)` | Inject an async validator. The built schema requires `parseAsync()`. |
| `.build()` | Return the final `ZodObject` or `ZodEffects` schema. |

---

## `createSv(config?)`

Create a pre-configured `sv()` factory with a shared `onUnknown` policy and optional coverage-check warnings. Use this at the application level so all forms share the same defaults.

```ts
import { createSv } from '@uuki/schemable-validator-client/zod'
import type { SvConfig } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

const sv = createSv({
  onUnknown: (key, field) => {
    if (field.format === 'hostname') {
      return z.string().regex(/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/)
    }
    throw new Error(`unsupported field "${key}"`)
  },
  check: true,  // console.warn for unsupported fields during build() — useful in dev
})

// Each form creates its own builder from the same factory
const schema = sv(jsonSchema).when().refine(myRule).build()
```

**`SvConfig`**

```ts
interface SvConfig {
  onUnknown?: 'warn' | 'throw' | ((key: string, field: PropertySchema) => ZodTypeAny)
  check?: boolean  // default: false
}
```

---

## `ZodRefiner` / `ZodAsyncRefiner`

Types for external validator functions injected via `.refine()` / `.refineAsync()`. The validator is a pure function with no builder coupling — import the type, implement the logic, inject it.

```ts
import type { ZodRefiner, ZodAsyncRefiner } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

// Synchronous validator
const checkDateRange: ZodRefiner = (data, ctx) => {
  if (data.start >= data.end) {
    ctx.addIssue({ code: 'custom', path: ['end'], message: 'Must be after start' })
  }
}

// Async validator — requires schema.parseAsync()
const checkAvailability: ZodAsyncRefiner = async (data, ctx) => {
  const res = await fetch(`/api/check?name=${data.username}`)
  const { ok } = await res.json()
  if (!ok) ctx.addIssue({ code: 'custom', path: ['username'], message: 'Taken' })
}
```

```ts
type ZodRefiner = (
  data: Record<string, unknown>,
  ctx:  z.RefinementCtx,
) => void

type ZodAsyncRefiner = (
  data: Record<string, unknown>,
  ctx:  z.RefinementCtx,
) => Promise<void>
```

---

## `toZodSchema(jsonSchema, options?)`

Convert an `ObjectSchema` to a Zod v4 `ZodObject`.

```ts
import { toZodSchema } from '@uuki/schemable-validator-client/zod'

const schema = toZodSchema(jsonSchema)
const result = schema.safeParse(formData)
```

**Options**

| Option | Type | Default | Description |
|---|---|---|---|
| `onUnknown` | `'warn' \| 'throw' \| (key, field) => ZodTypeAny` | `process.env.NODE_ENV === 'production' ? 'throw' : 'warn'` | Behaviour when a field has no Zod equivalent. |

| Value | Behaviour |
|---|---|
| `'warn'` | `console.warn` and fall back to `z.unknown()` |
| `'throw'` | Throw an `Error` immediately |
| `(key, field) => schema` | Return a custom schema for that field |

```ts
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
- `x-unmapped-fields` (SV::file(), SV::custom(), RespectRules::rule()) are skipped. Add them with `.extend()`.
- `format: 'hostname'` has no Zod built-in — use `onUnknown`.
- `x-when` / `if/then` conditionals are not mapped — add via `.superRefine()`.

---

## `checkZodSchema(jsonSchema)`

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
