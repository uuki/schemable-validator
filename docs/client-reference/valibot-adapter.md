# Valibot Adapter

```ts
import { sv, createSv, toValibotSchema, checkValibotSchema } from '@uuki/schemable-validator-client/valibot'
import type { ValibotRefiner, ValibotAsyncRefiner, SvConfig } from '@uuki/schemable-validator-client/valibot'
```

Requires `valibot` as a peer dependency (`pnpm add valibot`).

---

## `sv(jsonSchema)` / `createSv(config?)`

Fluent schema builder — same API as the Zod variant. Async entries are automatically detected: if any extended entry has `async: true`, `build()` selects `v.objectAsync` / `v.pipeAsync` without any extra configuration.

```ts
import { sv, createSv } from '@uuki/schemable-validator-client/valibot'
import * as v from 'valibot'

// Simple usage
const schema = sv(jsonSchema).when().refine(checkDates).build()
const result = v.safeParse(schema, formData)

// With async field (auto-detected)
const schema = sv(jsonSchema)
  .extend({ avatar: v.pipeAsync(v.instance(File), v.checkAsync(validateFile, 'Invalid')) })
  .when()
  .build()
const result = await v.safeParseAsync(schema, formData)

// Shared factory
const sv = createSv({ onUnknown: 'throw', check: true })
```

**Builder methods** — identical to the Zod builder:

| Method | Description |
|---|---|
| `.onUnknown(policy)` | Override `onUnknown` for this schema only. |
| `.extend(fields)` | Add or override fields. Async schemas (`v.pipeAsync`, `v.objectAsync`) are auto-detected. |
| `.when()` | Auto-apply all `x-when` conditions. |
| `.refine(fn)` | Inject a synchronous validator. |
| `.refineAsync(fn)` | Inject an async validator. Requires `v.safeParseAsync()`. |
| `.build()` | Return the final schema (sync or async depending on inputs). |

**`SvConfig`**

```ts
interface SvConfig {
  onUnknown?: 'warn' | 'throw' | ((key: string, field: PropertySchema) => GenericSchema)
  check?: boolean  // default: false
}
```

---

## `ValibotRefiner` / `ValibotAsyncRefiner`

Types for external validator functions. The context shape follows Valibot's `rawCheck` callback.

```ts
import type { ValibotRefiner, ValibotAsyncRefiner } from '@uuki/schemable-validator-client/valibot'

const checkDateRange: ValibotRefiner = ({ dataset, addIssue }) => {
  if (!dataset.typed) return
  const d = dataset.value as { start?: string; end?: string }
  if (d.start && d.end && d.start >= d.end) {
    addIssue({ message: 'end must be after start' })
  }
}

const checkAvailability: ValibotAsyncRefiner = async ({ dataset, addIssue }) => {
  if (!dataset.typed) return
  const d = dataset.value as { username: string }
  const res = await fetch(`/api/check?name=${d.username}`)
  const { ok } = await res.json()
  if (!ok) addIssue({ message: 'Taken' })
}
```

---

## `toValibotSchema(jsonSchema, options?)`

Convert an `ObjectSchema` to a Valibot v1 object schema.

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

## `checkValibotSchema(jsonSchema)`

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
