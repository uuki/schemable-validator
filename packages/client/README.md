# @uuki/schemable-validator-client

Client-side validation library for [schemable-validator](https://github.com/uuki/schemable-validator) JSON Schema output.

## Adapter coverage

Both adapters convert the JSON Schema output of `SchemaBuilder::toJsonSchema()` into native schemas.  
Coverage is measured against the 19 mappable rules in `RuleMapper.php` (`fileExt` is excluded by the PHP side via `x-unmapped-fields`).

### Zod (v4)

| Rule | JSON Schema field | Supported |
|---|---|:---:|
| `string` | `type: 'string'` | ✅ |
| `integer` | `type: 'integer'` | ✅ |
| `number` | `type: 'number'` | ✅ |
| `boolean` | `type: 'boolean'` | ✅ |
| `nullable()` | `type: ['X', 'null']` | ✅ |
| `length` | `minLength` / `maxLength` | ✅ |
| `min` / `max` | `minimum` / `maximum` | ✅ |
| `email` | `format: 'email'` | ✅ |
| `url` | `format: 'uri'` | ✅ |
| `date` | `format: 'date'` | ✅ |
| `dateTime` | `format: 'date-time'` | ✅ |
| `time` | `format: 'time'` | ✅ |
| `uuid` | `format: 'uuid'` | ✅ |
| `ipv4` | `format: 'ipv4'` | ✅ |
| `ipv6` | `format: 'ipv6'` | ✅ |
| `pattern` | `pattern` | ✅ |
| `slug` | `pattern: '^[a-z0-9]+...'` | ✅ |
| `in` | `enum` | ✅ |
| `ArraySchema` | `type: 'array'` + `items` / `minItems` / `maxItems` | ✅ |
| `domain` | `format: 'hostname'` | ⚠️ |

**18 / 19 — 94.7%**

`hostname` has no Zod built-in. Handle it via `onUnknown`:

```ts
import { toZodSchema } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

const schema = toZodSchema(jsonSchema, {
  onUnknown: (key, field) => {
    if (field.format === 'hostname') return z.string().regex(/^[a-z0-9.-]+$/)
    throw new Error(`[zod] unsupported field "${key}": ${JSON.stringify(field)}`)
  },
})
```

---

### Valibot (v1)

| Rule | JSON Schema field | Supported |
|---|---|:---:|
| `string` | `type: 'string'` | ✅ |
| `integer` | `type: 'integer'` | ✅ |
| `number` | `type: 'number'` | ✅ |
| `boolean` | `type: 'boolean'` | ✅ |
| `nullable()` | `type: ['X', 'null']` | ✅ |
| `length` | `minLength` / `maxLength` | ✅ |
| `min` / `max` | `minimum` / `maximum` | ✅ |
| `email` | `format: 'email'` | ✅ |
| `url` | `format: 'uri'` | ✅ |
| `date` | `format: 'date'` | ✅ |
| `dateTime` | `format: 'date-time'` | ✅ |
| `time` | `format: 'time'` | ✅ |
| `uuid` | `format: 'uuid'` | ✅ |
| `ipv4` | `format: 'ipv4'` | ✅ |
| `ipv6` | `format: 'ipv6'` | ✅ |
| `pattern` | `pattern` | ✅ |
| `slug` | `pattern: '^[a-z0-9]+...'` | ✅ |
| `in` | `enum` | ✅ |
| `ArraySchema` | `type: 'array'` + `items` / `minItems` / `maxItems` | ✅ |
| `domain` | `format: 'hostname'` | ⚠️ |

**18 / 19 — 94.7%**

`hostname` has no Valibot built-in. Handle it via `onUnknown`:

```ts
import { toValibotSchema } from '@uuki/schemable-validator-client/valibot'
import * as v from 'valibot'

const schema = toValibotSchema(jsonSchema, {
  onUnknown: (key, field) => {
    if (field.format === 'hostname') return v.pipe(v.string(), v.regex(/^[a-z0-9.-]+$/))
    throw new Error(`[valibot] unsupported field "${key}": ${JSON.stringify(field)}`)
  },
})
```

---

### `onUnknown` behaviour

All adapter functions accept an `onUnknown` option that controls what happens when an unsupported field is encountered.

| Value | Development (`NODE_ENV !== 'production'`) | Production (`NODE_ENV === 'production'`) |
|---|---|---|
| `'warn'` | `console.warn` + fall back to `unknown` | same |
| `'throw'` | throw | throw |
| `(key, field) => Schema` | call the function | same |
| _(default)_ | `'warn'` | `'throw'` |

The default resolves automatically from `process.env.NODE_ENV`, which bundlers (Vite, webpack) replace with a string literal at build time.

### `checkZodSchema` / `checkValibotSchema`

Dry-run functions that return a coverage report without throwing. Useful for fetch-based workflows where the schema is not known until runtime.

```ts
import { checkZodSchema } from '@uuki/schemable-validator-client/zod'

const report = checkZodSchema(jsonSchema)
// { supported: ['name', 'email'], unsupported: [{ key: 'host', field: {...}, reason: '...' }] }

if (report.unsupported.length) {
  console.warn('[schemable] unsupported fields:', report.unsupported)
}
```
