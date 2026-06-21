# クライアント

## 概要

`SchemaBuilder::toJsonSchema()` は PHP 側のバリデーションルールを標準 JSON Schema（draft 2020-12）オブジェクトとしてエクスポートします。JSON Schema に対応した任意のバリデーターをクライアント側で直接利用できます。

`@uuki/schemable-validator-client` には JSON Schema を Zod または Valibot のネイティブスキーマに変換するビルトイン**アダプター**も同梱されており、型推論やフレームワーク連携など、JSON Schema バリデーター単体では得られない機能を提供します。

---

## 基本的な使い方

出力は標準 JSON Schema であるため、準拠する任意のライブラリで検証できます。以下は [AJV](https://ajv.js.org/) を使った例で、アダプターは不要です。

```
pnpm add ajv ajv-formats
```

```ts
import Ajv from 'ajv'
import addFormats from 'ajv-formats'

const ajv = new Ajv()
addFormats(ajv)

// jsonSchema は PHP 側の SchemaBuilder::toJsonSchema() が返すオブジェクト
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

## アダプター

TypeScript の型推論やリアクティブフォームとの統合、カスタムリファイナーが必要な場合は、ビルトインアダプターを使います。推奨エントリポイントは `sv()` フルエントビルダーです。

以下のサンプルは最小限のインラインスキーマを使った完結した例です。実際の用途ではサーバーから `jsonSchema` を取得します。

:::code-group

```ts [Zod]
import { sv } from '@uuki/schemable-validator-client/zod'

const jsonSchema = {
  $schema: 'https://json-schema.org/draft/2020-12/schema',
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
  $schema: 'https://json-schema.org/draft/2020-12/schema',
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

## カスタムマッピング

アダプターは大半の PHP ルールを自動変換します。残りはビルダーのメソッドとオプションで対応します。

### 未対応ルールの扱い（`onUnknown`）

アダプターがマッピングできないフィールドに遭遇したとき、`onUnknown` オプションで挙動を制御します。

| 値 | 挙動 |
|---|---|
| `'warn'` | `console.warn` を出力し `unknown` にフォールバック |
| `'throw'` | 即座に `Error` を throw |
| `(key, field) => Schema` | 関数を呼び出し、返したスキーマを使用 |
| _(デフォルト)_ | 開発環境は `'warn'`、本番環境は `'throw'` |

デフォルト値は `process.env.NODE_ENV` から自動解決されます。Vite・webpack はビルド時にこの値を文字列リテラルに置換するため、追加設定なしで切り替わります。

:::code-group

```ts [Zod]
import { sv } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

const schema = sv(jsonSchema)
  .onUnknown((key, field) => {
    if (field.format === 'hostname') {
      return z.string().regex(/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/)
    }
    throw new Error(`[zod] 未対応フィールド "${key}": ${JSON.stringify(field)}`)
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
    throw new Error(`[valibot] 未対応フィールド "${key}": ${JSON.stringify(field)}`)
  })
  .build()
```

:::

### 条件付き必須（`when`）

`SchemaBuilder::when()` は `x-when` 配列を出力します。ビルダーの `.when()` を呼び出すと、スキーマ内のすべての条件を自動的にフィールドレベルの必須チェックとして適用します。

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

`.when()` は `x-when` の全エントリを読み取り、フィールドレベルのエラーとして適用します。`===`・`!==`・`>=`・`<=`・`>`・`<`・フィールド参照のすべての演算子に対応しています。

### ファイルフィールド（`x-unmapped-fields`）

`SV::file()` フィールドは PHP 側で JSON Schema 出力から除外され、`x-unmapped-fields` にリストされます。アダプターは自動的にスキップするため、`.extend()` で追加します。

:::code-group

```ts [Zod]
import { sv } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

const schema = sv(jsonSchema)
  .extend({ avatar: z.instanceof(File).refine((f) => f.size < 5_000_000, '最大 5 MB') })
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

### サーバー専用フィールドの acknowledge

`validateObject()` は、`x-unmapped-fields` に含まれるフィールド名のうち呼び出し側が acknowledge していないものについてコンソール警告を出力します。
サーバーでのみ検証するフィールドを明示することで、警告を抑制できます。

```ts
import { validateObject } from '@uuki/schemable-validator-client'

const result = validateObject(formData, schema, {
  acknowledgedServerFields: ['avatar', 'custom_check'],
})
```

Zod / Valibot アダプターでも、`createSv()` のファクトリ設定で同じオプションを使用できます。

```ts
import { createSv } from '@uuki/schemable-validator-client/zod'

const sv = createSv({
  check: true,
  acknowledgedServerFields: ['avatar'],
})
```

`acknowledgedServerFields` に含まれるフィールドは、`build()` 時の `x-custom-fields` 警告からも除外されます。
PHP 側でサーバー専用フィールドが追加されると、acknowledge されるまで警告が再び表示されるため、見落としを防ぐことができます。

### クロスフィールド制約（`RespectRules::rule`）

`RespectRules::rule()` は JSON Schema マッピングのない任意の Respect/Validation ルールをラップします。クライアント側の等価ロジックを純粋な関数として実装し、`.refine()` で注入します。

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
      message: 'パスワードと一致しません',
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
  if (d.confirm !== d.password) addIssue({ message: 'パスワードと一致しません' })
}

const schema = sv(jsonSchema).refine(checkConfirm).build()
```

:::

### ランタイムカバレッジチェック

`fetch` でスキーマを取得するパターンでは、実行時まで内容が不明です。`checkZodSchema` / `checkValibotSchema` を使うと、throw せずにカバレッジレポートを取得できます。

```ts
import { checkZodSchema }     from '@uuki/schemable-validator-client/zod'
import { checkValibotSchema } from '@uuki/schemable-validator-client/valibot'

const jsonSchema = await fetchSchema('/api/schema/contact')

const report = checkZodSchema(jsonSchema)
// { supported: ['name', 'email'], unsupported: [{ key: 'host', reason: 'format "hostname" ...' }] }

if (report.unsupported.length) {
  console.warn('[schemable] 未対応フィールド:', report.unsupported)
}
```

`createSv()` に `check: true` を渡すと、`build()` ごとに自動でチェックが走り、未対応フィールドを `console.warn` します。`createSv()` でファクトリを一度設定して全フォームで共有できます。

```ts
import { createSv } from '@uuki/schemable-validator-client/zod'
import type { ZodRefiner } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

// アプリ起動時に1度だけ設定 — 全フォームで共有
const sv = createSv({
  check: true,  // build() ごとに未対応フィールドを console.warn
  onUnknown: (key, field) => {
    // 開発中に surfacing したフォーマットを順次追加
    throw new Error(`未対応フィールド "${key}": ${field.format ?? field.type}`)
  },
})

async function buildSchema(url: string) {
  const jsonSchema = await fetchSchema(url)
  return sv(jsonSchema).when().build()
}
```

---

## 高度な検証パターン

JSON Schema では表現できない制約（リアルタイムデータへの問い合わせ、FE 固有のビジネスルール）は、ビルダーに組み合わせて実装します。

**設計原則：複雑なロジックはビルダーの外に置く。** バリデーター関数は純粋な関数として定義し、`.refine()` / `.refineAsync()` で注入します。ライブラリへの依存なし、ビルダーの内部実装にも依存しません。

### 非同期フィールド検証

ユーザー名の重複チェックなど、サーバーへの問い合わせが必要な検証を `.refineAsync()` で追加します。

:::code-group

```ts [Zod]
import { sv } from '@uuki/schemable-validator-client/zod'
import type { ZodAsyncRefiner } from '@uuki/schemable-validator-client/zod'

const checkUsernameAvailable: ZodAsyncRefiner = async (data, ctx) => {
  const res = await fetch(`/api/users/check?name=${encodeURIComponent(data.username as string)}`)
  const { available } = await res.json() as { available: boolean }
  if (!available) {
    ctx.addIssue({ code: 'custom', path: ['username'], message: 'このユーザー名は使用されています' })
  }
}

const jsonSchema = await fetchSchema('/api/schema/register')

// 非同期スキーマは parseAsync を使用
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
  if (!available) addIssue({ message: 'このユーザー名は使用されています' })
}

const jsonSchema = await fetchSchema('/api/schema/register')

// 非同期スキーマは safeParseAsync を使用
const schema = sv(jsonSchema).when().refineAsync(checkUsernameAvailable).build()
const result = await v.safeParseAsync(schema, formData)
```

:::

### クロスフィールド・ビジネスルール

「終了日は開始日より後であること」のような日付範囲チェックを `.refine()` で追加します。

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
      message: '開始日より後の日付を入力してください',
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
    addIssue({ message: '開始日より後の日付を入力してください' })
  }
}

const schema = sv(jsonSchema).when().refine(checkDateRange).build()
const result = v.safeParse(schema, formData)
```

:::

### 全レイヤーを組み合わせる

アダプター変換・`x-when` 条件・ファイルフィールド追加・FE 固有ビジネスルールを重ねた、fetch ベースフォームの完成形です。

```ts
import { createSv } from '@uuki/schemable-validator-client/zod'
import type { ZodRefiner } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

// アプリ起動時に1度だけ設定
const sv = createSv({
  check: true,
  onUnknown: (key, field) => {
    if (field.format === 'hostname') {
      return z.string().regex(/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/)
    }
    throw new Error(`未対応フィールド "${key}"`)
  },
})

// 外部バリデーター — ビルダーに依存しない純粋な関数
const checkDeliveryDate: ZodRefiner = (data, ctx) => {
  if (data.delivery_date && (data.delivery_date as string) < new Date().toISOString().slice(0, 10)) {
    ctx.addIssue({
      code: 'custom',
      path: ['delivery_date'],
      message: '配達日は本日以降の日付を選択してください',
    })
  }
}

async function buildOrderSchema() {
  const jsonSchema = await fetchSchema('/api/schema/order')

  return sv(jsonSchema)
    .extend({ receipt: z.instanceof(File).optional() })  // x-unmapped-fields のファイルフィールド
    .when()                                               // x-when の条件付き必須
    .refine(checkDeliveryDate)                            // FE ビジネスルール
    .build()
}
```

### カスタム Constraint

`validateObject` は各フィールドを **Constraint パイプライン** — `(state: FieldState) => FieldState` 型の純関数の連鎖 — で評価します。各関数は失敗時に `state.errors` にエラーを追加し、通過時はそのまま返します。エラーはすべて蓄積され、最初のエラーで短絡しません。

`composeConstraints` を使うことで、カスタム `Constraint` をビルトインルールと合成できます。

```ts
import {
  composeConstraints, constraintsFromSchema, validateObject,
} from '@uuki/schemable-validator-client'
import type { Constraint, ObjectSchema } from '@uuki/schemable-validator-client'

// Constraint は純関数: FieldState → FieldState
const checkJapanesePhone: Constraint = (state) =>
  /^(0\d{9,10}|0\d{1,4}-\d{1,4}-\d{3,4})$/.test(state.value)
    ? state
    : { ...state, errors: [...state.errors, '日本の電話番号形式で入力してください'] }

// 合成: ビルトインの文字列型チェック → カスタム電話番号フォーマットチェック
const phoneConstraint = composeConstraints([
  constraintsFromSchema({ type: 'string' }),
  checkJapanesePhone,
])
```

`validateObject` の結果にフィールド単位でマージして適用します。

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

→ 完全な実装サンプル: [サンプル — 4. カスタム Constraint の追加](/ja/examples/client#4-カスタム-constraint-の追加)

---

## ルールカバー率

`RuleMapper.php` の 19 個のマッピング可能なルールを基準にしています。  
（`SV::file()` / `RespectRules::rule()` は PHP 側で `x-unmapped-fields` に除外され、アダプターには渡されません。）

| PHP ルール | JSON Schema フィールド | Zod | Valibot |
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

**18 / 19 — 94.7%**（Zod・Valibot 共通）

`hostname` は Zod・Valibot ともにビルトインがないため、`onUnknown` で独自実装を提供します（前述）。
