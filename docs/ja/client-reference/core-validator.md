# コアバリデーター

```ts
import { validateObject, isAllValid, extractErrors } from '@uuki/schemable-validator-client'
```

---

## `validateObject(data, schema)`

フラットなキー/値レコードを `ObjectSchema` に対して検証します。フィールド名をキーとした `ValidationResult` を返します。

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

**パラメーター**

| | 型 | 説明 |
|---|---|---|
| `data` | `Record<string, string \| readonly string[]>` | フォーム値。チェックボックス・複数選択は `string[]` で渡します。 |
| `schema` | `ObjectSchema` | `SchemaBuilder::toJsonSchema()` の出力。 |

**戻り値** `ValidationResult` — `Record<string, FieldResult>`

**動作の注意点**
- 空の任意フィールドは常に有効です。
- `x-unmapped-fields`（SV::file、SV::respect）は `is_valid: true` として通過します。
- `x-when` の条件付き必須は自動的に評価されます。
- `x-when` がないスキーマでは `if/then` / `allOf` にフォールバックします。

---

## `isAllValid(result)`

`ValidationResult` のすべてのフィールドが有効なとき `true` を返します。

```ts
import { validateObject, isAllValid } from '@uuki/schemable-validator-client'

const result = validateObject(formData, schema)
if (isAllValid(result)) {
  await submitForm(formData)
}
```

---

## `extractErrors(result)`

無効なフィールドのみを抽出し、`value` と `is_valid` を除去して返します。エラーメッセージの描画に使います。

```ts
import { validateObject, extractErrors } from '@uuki/schemable-validator-client'

const errors = extractErrors(validateObject(formData, schema))
// { email: ['must be a valid email'], age: ['must be at least 18'] }

for (const [field, messages] of Object.entries(errors)) {
  showError(field, messages.join(', '))
}
```

**戻り値** `Record<string, readonly string[]>` — 失敗したフィールドのみ。
