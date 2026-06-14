# クライアントアダプター

`@uuki/schemable-validator-client` パッケージには、`SchemaBuilder::toJsonSchema()` の出力を Zod または Valibot のネイティブスキーマへ変換するビルトインアダプターが含まれています。

```ts
import { toZodSchema }     from '@uuki/schemable-validator-client/zod'
import { toValibotSchema } from '@uuki/schemable-validator-client/valibot'
```

---

## ルールカバー率

`RuleMapper.php` の 19 個のマッピング可能なルールを基準にしています。  
（`SV::file()` / `SV::respect()` は PHP 側で `x-unmapped-fields` に除外され、アダプターには渡されません。）

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

`hostname` は Zod・Valibot ともにビルトインがないため、`onUnknown` で独自実装を提供します（後述）。

---

## 未対応ルールの扱い（`onUnknown`）

アダプターがマッピングできないフィールドに遭遇したとき、`onUnknown` オプションで挙動を制御します。

| 値 | 挙動 |
|---|---|
| `'warn'` | `console.warn` を出力し `unknown` にフォールバック |
| `'throw'` | 即座に `Error` を throw |
| `(key, field) => Schema` | 関数を呼び出し、返したスキーマを使用 |
| _(デフォルト)_ | 開発環境は `'warn'`、本番環境は `'throw'` |

デフォルト値は `process.env.NODE_ENV` から自動解決されます。Vite・webpack はビルド時にこの値を文字列リテラルに置換するため、追加設定なしで切り替わります。

### カスタムマッピングの提供

```ts
import { toZodSchema } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

const schema = toZodSchema(jsonSchema, {
  onUnknown: (key, field) => {
    if (field.format === 'hostname') {
      return z.string().regex(/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/)
    }
    throw new Error(`[zod] 未対応フィールド "${key}": ${JSON.stringify(field)}`)
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
    throw new Error(`[valibot] 未対応フィールド "${key}": ${JSON.stringify(field)}`)
  },
})
```

---

## アダプターがマッピングしないルール

一部の PHP 側制約は JSON Schema に直接相当するものがないか、複雑すぎてロスレスに変換できません。これらはアダプターがスキップするため、変換後に手動で追加します。

### 条件付き必須（`when`）

`SchemaBuilder::when()` は `x-when`（および `===` 条件の `if/then`）を出力しますが、アダプターはこれらをスキップします。`!==` / `>=` / フィールド参照を含む条件セット全体を Zod・Valibot スキーマとしてロスレスに表現できないためです。

`.superRefine()` (Zod) または `v.forward()` + `v.partialCheck()` (Valibot) で手動実装します：

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
      message: 'type が company のとき必須です',
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
      'type が company のとき必須です',
    ),
    ['company_name'],
  ),
)
```

:::

#### `x-when` をスキーマから自動適用する

アダプターは `x-when` をスキップしますが、`x-when` 配列は JSON Schema オブジェクトに含まれています。以下のヘルパーはすべての条件を読み取り、フィールドレベルのエラーを自動で追加します — フォームごとに手動実装する必要がありません。

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
          ctx.addIssue({ code: z.ZodIssueCode.custom, path: [key], message: '必須項目です' })
        }
      }
    }
  })
}

// 静的スキーマでも fetch 取得スキーマでも動作
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
              message: '必須項目です',
              path: [{ key, type: 'object', origin: 'value', input: d, value: val }],
            })
          }
        }
      }
    }),
  )
}

// 使用例
const jsonSchema = await fetchSchema('/api/schema/order')
const base = toValibotSchema(jsonSchema)
const schema = jsonSchema['x-when']?.length
  ? applyWhenConditions(base, jsonSchema['x-when'])
  : base
```

:::

### ファイルフィールド（`x-unmapped-fields`）

`SV::file()` フィールドは PHP 側で JSON Schema 出力から除外され、`x-unmapped-fields` にリストされます。アダプターは自動的にスキップするため、必要に応じて手動で追加します：

:::code-group

```ts [Zod]
const schema = toZodSchema(jsonSchema).extend({
  avatar: z.instanceof(File).refine((f) => f.size < 5_000_000, '最大 5 MB'),
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

### クロスフィールド制約（`SV::respect`）

`SV::respect()` は JSON Schema マッピングのない任意の Respect/Validation ルールをラップします。`x-unmapped-fields` に出力されるため、クライアント側で同等の処理を実装します：

:::code-group

```ts [Zod]
// PHP: 'confirm' => SV::respect(v::equals($data['password']))
const schema = toZodSchema(jsonSchema).superRefine((data, ctx) => {
  if (data.confirm !== data.password) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['confirm'],
      message: 'パスワードと一致しません',
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
      'パスワードと一致しません',
    ),
    ['confirm'],
  ),
)
```

:::

---

## ランタイムカバレッジチェック

`fetch` でスキーマを取得するパターンでは、実行時まで内容が不明です。`checkZodSchema` / `checkValibotSchema` を使うと、throw せずにカバレッジレポートを取得できます：

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

### fetch パターンの推奨実装

```ts
async function buildSchema(url: string) {
  const jsonSchema = await fetchSchema(url)

  // フォームのレンダリング前にカバレッジの問題を検出
  const { unsupported } = checkZodSchema(jsonSchema)
  if (unsupported.length) {
    console.warn('[schemable] onUnknown が必要なフィールド:', unsupported)
  }

  return toZodSchema(jsonSchema, {
    // NODE_ENV から自動解決: 開発は 'warn'、本番は 'throw'
    onUnknown: (key, field) => {
      // 開発中に surfacing したフォーマットを順次追加
      throw new Error(`未対応フィールド "${key}": ${field.format ?? field.type}`)
    },
  })
}
```

---

## 高度な検証パターン

JSON Schema では表現できない制約（リアルタイムデータへの問い合わせ、FE 固有のビジネスルール）は、アダプター出力に重ねて実装します。

### 非同期フィールド検証

ユーザー名の重複チェックなど、サーバーへの問い合わせが必要な検証をアダプター生成スキーマに追加します。

:::code-group

```ts [Zod]
import { toZodSchema } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

const jsonSchema = await fetchSchema('/api/schema/register')

// .extend() でアダプター実行後に個別フィールドを上書き・追加
const schema = toZodSchema(jsonSchema).extend({
  username: z.string().min(3).refine(
    async (val) => {
      const res = await fetch(`/api/users/check?name=${encodeURIComponent(val)}`)
      const { available } = await res.json() as { available: boolean }
      return available
    },
    { message: 'このユーザー名は使用されています' },
  ),
})

// 非同期スキーマは parseAsync を使用
const result = await schema.safeParseAsync(formData)
```

```ts [Valibot]
import { toValibotSchema } from '@uuki/schemable-validator-client/valibot'
import * as v from 'valibot'

const jsonSchema = await fetchSchema('/api/schema/register')
const base = toValibotSchema(jsonSchema)

// base.entries を展開し、対象フィールドだけ非同期バリアントで上書き
const schema = v.objectAsync({
  ...base.entries,
  username: v.pipeAsync(
    v.string(),
    v.minLength(3),
    v.checkAsync(async (val) => {
      const res = await fetch(`/api/users/check?name=${encodeURIComponent(val)}`)
      const { available } = await res.json() as { available: boolean }
      return available
    }, 'このユーザー名は使用されています'),
  ),
})

const result = await v.safeParseAsync(schema, formData)
```

:::

### クロスフィールド・ビジネスルール

「終了日は開始日より後であること」のような日付範囲チェックは JSON Schema で表現できません。アダプター後に `superRefine` / `v.rawCheck` で追加します。

:::code-group

```ts [Zod]
const schema = toZodSchema(jsonSchema).superRefine((data, ctx) => {
  if (data.start_date && data.end_date && data.start_date >= data.end_date) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['end_date'],
      message: '開始日より後の日付を入力してください',
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
        message: '開始日より後の日付を入力してください',
        path: [{ key: 'end_date', type: 'object', origin: 'value', input: dataset.value, value: end_date }],
      })
    }
  }),
)
```

:::

### 全レイヤーを組み合わせる

アダプター・`x-when` 条件・FE 固有ビジネスルールを重ねた、fetch ベースフォームの完成形です。

```ts
import type { WhenCondition, WhenOp } from '@uuki/schemable-validator-client'
import { checkZodSchema, toZodSchema } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

// applyWhenConditions は前述のヘルパーを再利用

async function buildOrderSchema() {
  const jsonSchema = await fetchSchema('/api/schema/order')

  const { unsupported } = checkZodSchema(jsonSchema)
  if (unsupported.length) console.warn('[schemable]', unsupported)

  // 1. アダプター — JSON Schema 制約をすべてマップ
  const base = toZodSchema(jsonSchema, {
    onUnknown: (key, field) => {
      if (field.format === 'hostname') {
        return z.string().regex(/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/)
      }
      throw new Error(`未対応フィールド "${key}"`)
    },
  })

  // 2. x-when — PHP SchemaBuilder の条件付き必須フィールド
  const withWhen = jsonSchema['x-when']?.length
    ? applyWhenConditions(base, jsonSchema['x-when'])
    : base

  // 3. FE ビジネスルール — 配達日は今日以降であること
  return withWhen.superRefine((data, ctx) => {
    if (data.delivery_date && data.delivery_date < new Date().toISOString().slice(0, 10)) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['delivery_date'],
        message: '配達日は本日以降の日付を選択してください',
      })
    }
  })
}
```
