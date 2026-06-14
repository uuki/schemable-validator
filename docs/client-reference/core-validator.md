# Core Validator

```ts
import { validateObject, isAllValid, extractErrors } from '@uuki/schemable-validator-client'
```

---

## `validateObject(data, schema)`

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

## `isAllValid(result)`

Returns `true` when every field in a `ValidationResult` is valid.

```ts
import { validateObject, isAllValid } from '@uuki/schemable-validator-client'

const result = validateObject(formData, schema)
if (isAllValid(result)) {
  await submitForm(formData)
}
```

---

## `extractErrors(result)`

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
