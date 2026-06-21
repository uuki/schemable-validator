# Zod アダプター

```ts
import { sv, createSv, toZodSchema, checkZodSchema } from '@uuki/schemable-validator-client/zod'
import type { ZodRefiner, ZodAsyncRefiner, SvConfig } from '@uuki/schemable-validator-client/zod'
```

ピア依存として `zod` が必要です（`pnpm add zod`）。

---

## `sv(jsonSchema)`

フルエントスキーマビルダー。`createSv()(jsonSchema)` の糖衣構文です。

```ts
import { sv } from '@uuki/schemable-validator-client/zod'

const schema = sv(jsonSchema)
  .onUnknown(myFallback)                       // このスキーマのみ onUnknown を上書き
  .extend({ avatar: z.instanceof(File) })      // JSON Schema にないフィールドを追加
  .when()                                      // x-when 条件を自動適用
  .refine(checkDates)                          // 同期クロスフィールドバリデーター
  .refineAsync(checkName)                      // 非同期バリデーター（parseAsync 必須）
  .build()                                     // Zod スキーマを生成して返す
```

**メソッド呼び出し順序は自由です。** `build()` は常に正しい順序でフェーズを適用します。

**ビルダーメソッド**

| メソッド | 説明 |
|---|---|
| `.onUnknown(policy)` | このスキーマのみ `onUnknown` を上書き。 |
| `.extend(fields)` | JSON Schema にないフィールドを追加・上書き（SV::file アップロード等）。 |
| `.when()` | スキーマの `x-when` 条件付き必須をすべて自動適用。 |
| `.refine(fn)` | 同期バリデーターを注入。ロジックはビルダーの外で実装。 |
| `.refineAsync(fn)` | 非同期バリデーターを注入。生成スキーマは `parseAsync()` 必須。 |
| `.build()` | 最終的な `ZodObject` または `ZodEffects` スキーマを返す。 |

---

## `createSv(config?)`

共通の `onUnknown` ポリシーとオプションのカバレッジチェックを持つ `sv()` ファクトリを作成します。アプリレベルで一度設定し、全フォームで共有します。

```ts
import { createSv } from '@uuki/schemable-validator-client/zod'
import type { SvConfig } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

const sv = createSv({
  onUnknown: (key, field) => {
    if (field.format === 'hostname') {
      return z.string().regex(/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/)
    }
    throw new Error(`未対応フィールド "${key}"`)
  },
  check: true,  // build() ごとに未対応フィールドを console.warn — 開発時に有効
})

// 各フォームで同じファクトリからビルダーを作成
const schema = sv(jsonSchema).when().refine(myRule).build()
```

**`SvConfig`**

```ts
interface SvConfig {
  onUnknown?: 'warn' | 'throw' | ((key: string, field: PropertySchema) => ZodTypeAny)
  check?: boolean  // デフォルト: false
}
```

---

## `ZodRefiner` / `ZodAsyncRefiner`

`.refine()` / `.refineAsync()` で注入する外部バリデーター関数の型。純粋な関数として実装し、型だけをインポートします。

```ts
import type { ZodRefiner, ZodAsyncRefiner } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

// 同期バリデーター
const checkDateRange: ZodRefiner = (data, ctx) => {
  if (data.start >= data.end) {
    ctx.addIssue({ code: 'custom', path: ['end'], message: '開始日より後を指定してください' })
  }
}

// 非同期バリデーター — schema.parseAsync() 必須
const checkAvailability: ZodAsyncRefiner = async (data, ctx) => {
  const res = await fetch(`/api/check?name=${data.username}`)
  const { ok } = await res.json()
  if (!ok) ctx.addIssue({ code: 'custom', path: ['username'], message: '使用済みです' })
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

`ObjectSchema` を Zod v4 の `ZodObject` に変換します。

```ts
import { toZodSchema } from '@uuki/schemable-validator-client/zod'

const schema = toZodSchema(jsonSchema)
const result = schema.safeParse(formData)
```

**オプション**

| オプション | 型 | デフォルト | 説明 |
|---|---|---|---|
| `onUnknown` | `'warn' \| 'throw' \| (key, field) => ZodTypeAny` | `process.env.NODE_ENV === 'production' ? 'throw' : 'warn'` | Zod に対応するフィールドがない場合の挙動。 |

| 値 | 挙動 |
|---|---|
| `'warn'` | `console.warn` を出力し `z.unknown()` にフォールバック |
| `'throw'` | 即座に `Error` を throw |
| `(key, field) => schema` | カスタムスキーマを返す |

```ts
const schema = toZodSchema(jsonSchema, {
  onUnknown: (key, field) => {
    if (field.format === 'hostname') {
      return z.string().regex(/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/)
    }
    throw new Error(`未対応フィールド "${key}"`)
  },
})
```

**制限事項**
- `x-unmapped-fields`（SV::file()、SV::custom()、RespectRules::rule()）はスキップされます。`.extend()` で追加してください。
- `format: 'hostname'` に Zod のビルトインはありません。`onUnknown` で対応します。
- `x-when` / `if/then` の条件付き必須はマッピングされません。`.superRefine()` で追加します。

---

## `checkZodSchema(jsonSchema)`

ドライラン：スキーマを構築せずに、どのフィールドがマッピング可能かをレポートします。throw しません。

```ts
import { checkZodSchema } from '@uuki/schemable-validator-client/zod'

const { supported, unsupported } = checkZodSchema(jsonSchema)
// supported:   ['name', 'email', 'age']
// unsupported: [{ key: 'host', field: {...}, reason: 'format "hostname" has no built-in Zod equivalent' }]

if (unsupported.length) {
  console.warn('[schemable] onUnknown が必要なフィールド:', unsupported)
}
```

**戻り値** `ZodSchemaReport`

```ts
interface ZodSchemaReport {
  supported:   string[]
  unsupported: { key: string; field: PropertySchema; reason: string }[]
}
```
