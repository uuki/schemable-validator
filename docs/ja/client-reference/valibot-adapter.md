# Valibot アダプター

```ts
import { sv, createSv, toValibotSchema, checkValibotSchema } from '@uuki/schemable-validator-client/valibot'
import type { ValibotRefiner, ValibotAsyncRefiner, SvConfig } from '@uuki/schemable-validator-client/valibot'
```

ピア依存として `valibot` が必要です（`pnpm add valibot`）。

---

## `sv(jsonSchema)` / `createSv(config?)`

Zod バリアントと同じフルエントビルダー API です。非同期エントリを自動検出します。拡張フィールドに `async: true` のスキーマが含まれていれば、`build()` が自動的に `v.objectAsync` / `v.pipeAsync` を選択します。

```ts
import { sv, createSv } from '@uuki/schemable-validator-client/valibot'
import * as v from 'valibot'

// シンプルな使用例
const schema = sv(jsonSchema).when().refine(checkDates).build()
const result = v.safeParse(schema, formData)

// 非同期フィールドあり（自動検出）
const schema = sv(jsonSchema)
  .extend({ avatar: v.pipeAsync(v.instance(File), v.checkAsync(validateFile, '無効なファイル')) })
  .when()
  .build()
const result = await v.safeParseAsync(schema, formData)

// 共有ファクトリ
const sv = createSv({ onUnknown: 'throw', check: true })
```

**ビルダーメソッド** — Zod ビルダーと同一：

| メソッド | 説明 |
|---|---|
| `.onUnknown(policy)` | このスキーマのみ `onUnknown` を上書き。 |
| `.extend(fields)` | フィールドを追加・上書き。非同期スキーマは自動検出。 |
| `.when()` | `x-when` 条件をすべて自動適用。 |
| `.refine(fn)` | 同期バリデーターを注入。 |
| `.refineAsync(fn)` | 非同期バリデーターを注入。`v.safeParseAsync()` 必須。 |
| `.build()` | 入力に応じて同期または非同期スキーマを返す。 |

**`SvConfig`**

```ts
interface SvConfig {
  onUnknown?: 'warn' | 'throw' | ((key: string, field: PropertySchema) => GenericSchema)
  check?: boolean  // デフォルト: false
}
```

---

## `ValibotRefiner` / `ValibotAsyncRefiner`

`.refine()` / `.refineAsync()` で注入する外部バリデーター関数の型。コンテキスト形状は Valibot の `rawCheck` コールバックに準拠します。

```ts
import type { ValibotRefiner, ValibotAsyncRefiner } from '@uuki/schemable-validator-client/valibot'

const checkDateRange: ValibotRefiner = ({ dataset, addIssue }) => {
  if (!dataset.typed) return
  const d = dataset.value as { start?: string; end?: string }
  if (d.start && d.end && d.start >= d.end) {
    addIssue({ message: '開始日より後を指定してください' })
  }
}

const checkAvailability: ValibotAsyncRefiner = async ({ dataset, addIssue }) => {
  if (!dataset.typed) return
  const d = dataset.value as { username: string }
  const res = await fetch(`/api/check?name=${d.username}`)
  const { ok } = await res.json()
  if (!ok) addIssue({ message: '使用済みです' })
}
```

---

## `toValibotSchema(jsonSchema, options?)`

`ObjectSchema` を Valibot v1 のオブジェクトスキーマに変換します。

```ts
import { toValibotSchema } from '@uuki/schemable-validator-client/valibot'
import * as v from 'valibot'

const schema = toValibotSchema(jsonSchema)
const result = v.safeParse(schema, formData)
```

**オプション**

| オプション | 型 | デフォルト | 説明 |
|---|---|---|---|
| `onUnknown` | `'warn' \| 'throw' \| (key, field) => GenericSchema` | `process.env.NODE_ENV === 'production' ? 'throw' : 'warn'` | Valibot に対応するフィールドがない場合の挙動。 |

```ts
import * as v from 'valibot'

const schema = toValibotSchema(jsonSchema, {
  onUnknown: (key, field) => {
    if (field.format === 'hostname') {
      return v.pipe(v.string(), v.regex(/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/))
    }
    throw new Error(`未対応フィールド "${key}"`)
  },
})
```

**制限事項**
- `x-unmapped-fields` はスキップされます。`base.entries` を展開して `v.object({ ...base.entries, myField: ... })` で追加します。
- `format: 'hostname'` に Valibot のビルトインはありません。`onUnknown` で対応します。
- `x-when` / `if/then` の条件付き必須はマッピングされません。`v.rawCheck` で追加します。

---

## `checkValibotSchema(jsonSchema)`

Valibot 用の `checkZodSchema` 相当のドライランです。

```ts
import { checkValibotSchema } from '@uuki/schemable-validator-client/valibot'

const { supported, unsupported } = checkValibotSchema(jsonSchema)
```

**戻り値** `ValibotSchemaReport`

```ts
interface ValibotSchemaReport {
  supported:   string[]
  unsupported: { key: string; field: PropertySchema; reason: string }[]
}
```
