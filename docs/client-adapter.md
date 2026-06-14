# Client Adapter

The `@uuki/schemable-validator-client` package ships built-in adapters that convert the JSON Schema output of `SchemaBuilder::toJsonSchema()` into native Zod or Valibot schemas.

```ts
import { toZodSchema }     from '@uuki/schemable-validator-client/zod'
import { toValibotSchema } from '@uuki/schemable-validator-client/valibot'
```

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

`hostname` has no built-in equivalent in Zod or Valibot. Use `onUnknown` to supply a custom mapping (see below).

---

## Handling unsupported rules (`onUnknown`)

When the adapter encounters a field it cannot map, the `onUnknown` option controls what happens.

| Value | Behaviour |
|---|---|
| `'warn'` | `console.warn` and fall back to `unknown` |
| `'throw'` | throw an `Error` immediately |
| `(key, field) => Schema` | call the function and use the returned schema |
| _(default)_ | `'warn'` in development, `'throw'` in production |

The default resolves from `process.env.NODE_ENV`, which Vite and webpack replace with a string literal at build time — no extra configuration needed.

### Supplying a custom mapping

```ts
import { toZodSchema } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

const schema = toZodSchema(jsonSchema, {
  onUnknown: (key, field) => {
    if (field.format === 'hostname') {
      return z.string().regex(/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/)
    }
    throw new Error(`[zod] unsupported field "${key}": ${JSON.stringify(field)}`)
  },
})
```

```ts
import { toValibotSchema } from '@uuki/schemable-validator-client/valibot'
import * as v from 'valibot'

const schema = toValibotSchema(jsonSchema, {
  onUnknown: (key, field) => {
    if (field.format === 'hostname') {
      return v.pipe(v.string(), v.regex(/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/))
    }
    throw new Error(`[valibot] unsupported field "${key}": ${JSON.stringify(field)}`)
  },
})
```

---

## Rules not mapped by adapters

Some PHP-side constraints have no direct JSON Schema equivalent or are intentionally complex. The adapters do not map them — they must be applied manually after conversion.

### Conditional requirements (`when`)

`SchemaBuilder::when()` emits `x-when` (and `if/then` for literal `===` conditions). The adapters skip these blocks because the full condition set (`!==`, `>=`, field references) cannot be represented as a lossless Zod/Valibot schema.

Apply them manually with `.superRefine()` (Zod) or `v.forward()` + `v.partialCheck()` (Valibot):

:::code-group

```ts [Zod]
import { toZodSchema } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

// PHP: ->when('type', SV::equal('company'), ['company_name'])
const schema = toZodSchema(jsonSchema).superRefine((data, ctx) => {
  if (data.type === 'company' && !data.company_name) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['company_name'],
      message: 'Required when type is company',
    })
  }
})
```

```ts [Valibot]
import { toValibotSchema } from '@uuki/schemable-validator-client/valibot'
import * as v from 'valibot'

// PHP: ->when('type', SV::equal('company'), ['company_name'])
const schema = v.pipe(
  toValibotSchema(jsonSchema),
  v.forward(
    v.partialCheck(
      [['company_name']],
      (d) => !(d.type === 'company' && !d.company_name),
      'Required when type is company',
    ),
    ['company_name'],
  ),
)
```

:::

#### Auto-applying `x-when` from the schema

When the schema is fetched at runtime, the `x-when` array is still present in the JSON Schema object. The helper below reads every condition and adds the corresponding field-level error automatically — no per-form manual coding needed.

:::code-group

```ts [Zod]
import type { WhenCondition, WhenOp } from '@uuki/schemable-validator-client'
import { toZodSchema } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

function evalOp(a: unknown, op: WhenOp, b: unknown): boolean {
  switch (op) {
    case '===': return a === b
    case '!==': return a !== b
    case '>=':  return (a as number) >= (b as number)
    case '<=':  return (a as number) <= (b as number)
    case '>':   return (a as number) >  (b as number)
    case '<':   return (a as number) <  (b as number)
  }
}

function applyWhenConditions(
  schema: z.ZodObject<Record<string, z.ZodTypeAny>>,
  conditions: readonly WhenCondition[],
) {
  return schema.superRefine((data, ctx) => {
    const d = data as Record<string, unknown>
    for (const cond of conditions) {
      const rhs = 'equalsField' in cond ? d[cond.equalsField] : cond.equals
      if (!evalOp(d[cond.field], cond.op, rhs)) continue
      for (const key of cond.require) {
        const val = d[key]
        if (val === undefined || val === null || val === '') {
          ctx.addIssue({ code: z.ZodIssueCode.custom, path: [key], message: 'Required' })
        }
      }
    }
  })
}

// Usage — works with a static schema or a runtime-fetched one
const jsonSchema = await fetchSchema('/api/schema/order')
const base = toZodSchema(jsonSchema)
const schema = jsonSchema['x-when']?.length
  ? applyWhenConditions(base, jsonSchema['x-when'])
  : base
```

```ts [Valibot]
import type { WhenCondition, WhenOp } from '@uuki/schemable-validator-client'
import { toValibotSchema } from '@uuki/schemable-validator-client/valibot'
import * as v from 'valibot'

function evalOp(a: unknown, op: WhenOp, b: unknown): boolean {
  switch (op) {
    case '===': return a === b
    case '!==': return a !== b
    case '>=':  return (a as number) >= (b as number)
    case '<=':  return (a as number) <= (b as number)
    case '>':   return (a as number) >  (b as number)
    case '<':   return (a as number) <  (b as number)
  }
}

function applyWhenConditions(
  schema: ReturnType<typeof toValibotSchema>,
  conditions: readonly WhenCondition[],
) {
  return v.pipe(
    schema,
    v.rawCheck(({ dataset, addIssue }) => {
      if (!dataset.typed) return
      const d = dataset.value as Record<string, unknown>
      for (const cond of conditions) {
        const rhs = 'equalsField' in cond ? d[cond.equalsField] : cond.equals
        if (!evalOp(d[cond.field], cond.op, rhs)) continue
        for (const key of cond.require) {
          const val = d[key]
          if (val === undefined || val === null || val === '') {
            addIssue({
              message: 'Required',
              path: [{ key, type: 'object', origin: 'value', input: d, value: val }],
            })
          }
        }
      }
    }),
  )
}

// Usage
const jsonSchema = await fetchSchema('/api/schema/order')
const base = toValibotSchema(jsonSchema)
const schema = jsonSchema['x-when']?.length
  ? applyWhenConditions(base, jsonSchema['x-when'])
  : base
```

:::

### File fields (`x-unmapped-fields`)

`SV::file()` fields are excluded from the JSON Schema output by the PHP side and listed in `x-unmapped-fields`. The adapters skip them automatically. Add them back with your own file schema:

:::code-group

```ts [Zod]
const schema = toZodSchema(jsonSchema).extend({
  avatar: z.instanceof(File).refine((f) => f.size < 5_000_000, 'Max 5 MB'),
})
```

```ts [Valibot]
const base = toValibotSchema(jsonSchema)
const schema = v.object({
  ...base.entries,
  avatar: v.pipe(v.instance(File), v.maxSize(5_000_000)),
})
```

:::

### Cross-field constraints (`SV::respect`)

`SV::respect()` wraps arbitrary Respect/Validation rules that have no JSON Schema mapping. These appear in `x-unmapped-fields` and require client-side equivalents:

:::code-group

```ts [Zod]
// PHP: 'confirm' => SV::respect(v::equals($data['password']))
const schema = toZodSchema(jsonSchema).superRefine((data, ctx) => {
  if (data.confirm !== data.password) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['confirm'],
      message: 'Must match password',
    })
  }
})
```

```ts [Valibot]
const schema = v.pipe(
  toValibotSchema(jsonSchema),
  v.forward(
    v.partialCheck(
      [['confirm'], ['password']],
      (d) => d.confirm === d.password,
      'Must match password',
    ),
    ['confirm'],
  ),
)
```

:::

---

## Runtime coverage check

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

### Recommended pattern for fetch-based forms

```ts
async function buildSchema(url: string) {
  const jsonSchema = await fetchSchema(url)

  // Surface coverage gaps early — before the form renders
  const { unsupported } = checkZodSchema(jsonSchema)
  if (unsupported.length) {
    console.warn('[schemable] fields needing onUnknown:', unsupported)
  }

  return toZodSchema(jsonSchema, {
    // 'warn' in dev, 'throw' in prod — resolved automatically from NODE_ENV
    onUnknown: (key, field) => {
      // add per-format fallbacks here as they surface in dev
      throw new Error(`unsupported field "${key}": ${field.format ?? field.type}`)
    },
  })
}
```

---

## Advanced validation patterns

Some constraints cannot be expressed in a JSON Schema at all — they depend on real-time data, user state, or business rules that live entirely on the FE. These patterns compose with the adapter output.

### Async field validation

Check a value against the server (e.g. username availability) by extending the adapter-generated schema with an async field.

:::code-group

```ts [Zod]
import { toZodSchema } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

const jsonSchema = await fetchSchema('/api/schema/register')

// .extend() overrides or adds individual fields after the adapter runs
const schema = toZodSchema(jsonSchema).extend({
  username: z.string().min(3).refine(
    async (val) => {
      const res = await fetch(`/api/users/check?name=${encodeURIComponent(val)}`)
      const { available } = await res.json() as { available: boolean }
      return available
    },
    { message: 'Username is already taken' },
  ),
})

// Async schemas require parseAsync
const result = await schema.safeParseAsync(formData)
```

```ts [Valibot]
import { toValibotSchema } from '@uuki/schemable-validator-client/valibot'
import * as v from 'valibot'

const jsonSchema = await fetchSchema('/api/schema/register')
const base = toValibotSchema(jsonSchema)

// Spread base.entries and override the target field with an async variant
const schema = v.objectAsync({
  ...base.entries,
  username: v.pipeAsync(
    v.string(),
    v.minLength(3),
    v.checkAsync(async (val) => {
      const res = await fetch(`/api/users/check?name=${encodeURIComponent(val)}`)
      const { available } = await res.json() as { available: boolean }
      return available
    }, 'Username is already taken'),
  ),
})

const result = await v.safeParseAsync(schema, formData)
```

:::

### Cross-field business rules

Rules like date-range ordering ("end must be after start") cannot be expressed in JSON Schema. Add them with `superRefine` / `v.rawCheck` after the adapter:

:::code-group

```ts [Zod]
const schema = toZodSchema(jsonSchema).superRefine((data, ctx) => {
  if (data.start_date && data.end_date && data.start_date >= data.end_date) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['end_date'],
      message: 'Must be after start date',
    })
  }
})
```

```ts [Valibot]
const schema = v.pipe(
  toValibotSchema(jsonSchema),
  v.rawCheck(({ dataset, addIssue }) => {
    if (!dataset.typed) return
    const { start_date, end_date } = dataset.value as { start_date?: string; end_date?: string }
    if (start_date && end_date && start_date >= end_date) {
      addIssue({
        message: 'Must be after start date',
        path: [{ key: 'end_date', type: 'object', origin: 'value', input: dataset.value, value: end_date }],
      })
    }
  }),
)
```

:::

### Composing all layers

A complete fetch-based form that layers the adapter, `x-when` conditions, and a custom business rule:

```ts
import type { WhenCondition, WhenOp } from '@uuki/schemable-validator-client'
import { checkZodSchema, toZodSchema } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

// Re-use the applyWhenConditions helper shown earlier in this page

async function buildOrderSchema() {
  const jsonSchema = await fetchSchema('/api/schema/order')

  const { unsupported } = checkZodSchema(jsonSchema)
  if (unsupported.length) console.warn('[schemable]', unsupported)

  // 1. Adapter — maps all JSON Schema constraints
  const base = toZodSchema(jsonSchema, {
    onUnknown: (key, field) => {
      if (field.format === 'hostname') {
        return z.string().regex(/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/)
      }
      throw new Error(`unsupported field "${key}"`)
    },
  })

  // 2. x-when — conditional required fields from PHP SchemaBuilder
  const withWhen = jsonSchema['x-when']?.length
    ? applyWhenConditions(base, jsonSchema['x-when'])
    : base

  // 3. FE business rule — delivery_date must be in the future
  return withWhen.superRefine((data, ctx) => {
    if (data.delivery_date && data.delivery_date < new Date().toISOString().slice(0, 10)) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['delivery_date'],
        message: 'Delivery date must be today or later',
      })
    }
  })
}
```
