# クライアント API リファレンス

`@uuki/schemable-validator-client` は4つのエクスポートグループを持ちます。

| インポートパス | 内容 |
|---|---|
| `@uuki/schemable-validator-client` | `validateObject`、Result プリミティブ、Constraint パイプライン、スキーマ型 |
| `@uuki/schemable-validator-client/zod` | `toZodSchema`、`checkZodSchema` |
| `@uuki/schemable-validator-client/valibot` | `toValibotSchema`、`checkValibotSchema` |

---

## コアバリデーター

### `validateObject(data, schema)`

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

### `isAllValid(result)`

`ValidationResult` のすべてのフィールドが有効なとき `true` を返します。

```ts
import { validateObject, isAllValid } from '@uuki/schemable-validator-client'

const result = validateObject(formData, schema)
if (isAllValid(result)) {
  await submitForm(formData)
}
```

---

### `extractErrors(result)`

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

---

## Result プリミティブ

Railway Oriented Programming (ROP) ヘルパー。`Result<A, E>` は `Ok<A>` または `Err<E>` のいずれかであり、例外も `null` も使いません。

### `ok(value)` / `err(error)`

2つのバリアントを生成します。

```ts
import { ok, err } from '@uuki/schemable-validator-client'

const success = ok('validated value')   // Ok<string>
const failure = err(['is required'])    // Err<string[]>
```

---

### `isOk(result)` / `isErr(result)`

型を絞り込むガード関数。

```ts
import { isOk, isErr } from '@uuki/schemable-validator-client'

if (isOk(result)) {
  console.log(result.value)   // Ok<A> に絞り込まれる
}
if (isErr(result)) {
  console.log(result.error)   // Err<E> に絞り込まれる
}
```

---

### `map(result, fn)`

`Ok` の値を変換し、`Err` はそのまま通過させます。

```ts
import { ok, err, map } from '@uuki/schemable-validator-client'

const r = ok('  hello  ')
const trimmed = map(r, (s) => s.trim())            // Ok<'hello'>
const failed  = map(err('oops'), (s) => s.trim())  // Err<'oops'>
```

---

### `flatMap(result, fn)`

`Ok` の値を別の `Result` を返す関数に渡します。`Err` の場合は短絡します。

```ts
import { ok, err, flatMap } from '@uuki/schemable-validator-client'

const parseAge = (s: string) =>
  Number.isInteger(+s) ? ok(+s) : err('not a number')

const checkAdult = (n: number) =>
  n >= 18 ? ok(n) : err('must be at least 18')

const result = flatMap(parseAge('21'), checkAdult)  // Ok<21>
const failed = flatMap(parseAge('abc'), checkAdult) // Err<'not a number'>
```

---

### `mapErr(result, fn)`

`Err` の値を変換し、`Ok` はそのまま通過させます。エラーメッセージの翻訳などに使います。

```ts
import { err, mapErr } from '@uuki/schemable-validator-client'

const translated = mapErr(
  err('is required'),
  (msg) => `このフィールドは${msg}`,
)
// Err<'このフィールドはis required'>
```

---

### `getOrElse(result, fallback)`

`Ok` の値を取り出し、`Err` の場合はフォールバック値を返します。

```ts
import { ok, err, getOrElse } from '@uuki/schemable-validator-client'

getOrElse(ok('Alice'), 'unknown')    // 'Alice'
getOrElse(err('missing'), 'unknown') // 'unknown'
```

---

## Constraint パイプライン

`validateObject` が内部で使う低レベルの構成要素です。カスタムフィールド検証ロジックが必要なときに個別にエクスポートして使います。

### `constraintsFromSchema(schema)`

`PropertySchema` から単一の合成済み `Constraint` を生成します。`validateObject` が内部で呼び出している関数と同一です。

```ts
import { constraintsFromSchema } from '@uuki/schemable-validator-client'
import type { PropertySchema } from '@uuki/schemable-validator-client'

const schema: PropertySchema = { type: 'string', format: 'email', minLength: 5 }
const validate = constraintsFromSchema(schema)

const result = validate({ value: 'bad', errors: [] })
// { value: 'bad', errors: ['must be a valid email'] }
```

---

### `composeConstraints(constraints)`

複数の `Constraint` を1つに合成します。すべての制約が順番に実行され、エラーは蓄積されます（最初のエラーで短絡しません）。

```ts
import { composeConstraints, checkMinLength, checkFormat } from '@uuki/schemable-validator-client'

const validate = composeConstraints([
  checkMinLength(3),
  checkFormat('email'),
])

validate({ value: 'x', errors: [] })
// { value: 'x', errors: [
//     'must be at least 3 characters long',
//     'must be a valid email'
//   ] }
```

---

### 個別の Constraint ファクトリー

各ファクトリーは `Constraint` — `(state: FieldState) => FieldState` を返します。

| 関数 | 説明 |
|---|---|
| `checkType(type)` | `integer` / `number` / `boolean` の型変換を検証。文字列は常に受け入れます。 |
| `checkMinLength(min)` | 文字列長 ≥ min。 |
| `checkMaxLength(max)` | 文字列長 ≤ max。 |
| `checkMinimum(min)` | 数値 ≥ min。 |
| `checkMaximum(max)` | 数値 ≤ max。 |
| `checkFormat(format)` | ビルトインのフォーマット正規表現で検証（下表参照）。 |
| `checkPattern(pattern, maxLen?)` | 任意の正規表現文字列でテスト。`maxLen`（デフォルト `500`）超の入力はスキップ（ReDoS 対策）。 |
| `checkEnum(values)` | 値がリストに含まれているか検証。 |

**`checkFormat` のビルトインフォーマット**

| フォーマット | パターン |
|---|---|
| `email` | `local@domain.tld` — 制御文字・ゼロ幅 Unicode を除外 |
| `uri` | `https?://…` — 制御文字を除外 |
| `date` | `YYYY-MM-DD` |
| `date-time` | `YYYY-MM-DDTHH:MM:SS[.ms](Z\|±HH:MM)` |
| `time` | `HH:MM:SS[.ms][Z\|±HH:MM]` |
| `uuid` | RFC 4122 |
| `ipv4` | ドット区切り10進数 |
| `ipv6` | フル / 省略形 |
| `hostname` | `label.label.tld` |

```ts
import { checkFormat, checkMinLength, composeConstraints } from '@uuki/schemable-validator-client'

const emailField = composeConstraints([checkMinLength(1), checkFormat('email')])

emailField({ value: '', errors: [] })
// { value: '', errors: ['must be at least 1 character long'] }

emailField({ value: 'not-an-email', errors: [] })
// { value: 'not-an-email', errors: ['must be a valid email'] }
```

---

### `PATTERN_MAX_INPUT_LENGTH`

```ts
export const PATTERN_MAX_INPUT_LENGTH = 500
```

`checkPattern` がクライアント側の正規表現評価をスキップするデフォルトの最大入力長。必要に応じて上書きできます。

```ts
import { checkPattern } from '@uuki/schemable-validator-client'

// 長さに関わらず常に評価する
const strictSlug = checkPattern('^[a-z0-9-]+$', Infinity)
```

---

## Zod アダプター

```ts
import { toZodSchema, checkZodSchema } from '@uuki/schemable-validator-client/zod'
```

ピア依存として `zod` が必要です（`pnpm add zod`）。

### `toZodSchema(jsonSchema, options?)`

`ObjectSchema` を Zod v4 の `ZodObject` に変換します。

```ts
import { toZodSchema } from '@uuki/schemable-validator-client/zod'
import { z } from 'zod'

const schema = toZodSchema(jsonSchema)
const result = schema.safeParse(formData)
```

**オプション**

| オプション | 型 | デフォルト | 説明 |
|---|---|---|---|
| `onUnknown` | `'warn' \| 'throw' \| (key, field) => ZodTypeAny` | `process.env.NODE_ENV === 'production' ? 'throw' : 'warn'` | Zod に対応するフィールドがない場合の挙動。 |

**`onUnknown` の値**

| 値 | 挙動 |
|---|---|
| `'warn'` | `console.warn` を出力し `z.unknown()` にフォールバック |
| `'throw'` | 即座に `Error` を throw |
| `(key, field) => schema` | カスタムスキーマを返す |

```ts
// 未対応フォーマットをカスタムマッピングで対応
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
- `x-unmapped-fields`（SV::file、SV::respect）はスキップされます。`.extend()` で追加してください。
- `format: 'hostname'` に Zod のビルトインはありません。`onUnknown` で対応します。
- `x-when` / `if/then` の条件付き必須はマッピングされません。`.superRefine()` で追加します。

---

### `checkZodSchema(jsonSchema)`

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

---

## Valibot アダプター

```ts
import { toValibotSchema, checkValibotSchema } from '@uuki/schemable-validator-client/valibot'
```

ピア依存として `valibot` が必要です（`pnpm add valibot`）。

### `toValibotSchema(jsonSchema, options?)`

`ObjectSchema` を Valibot v1 の `ObjectSchema` に変換します。

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

### `checkValibotSchema(jsonSchema)`

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

---

## 型リファレンス

すべての型はメインエントリから再エクスポートされています。

```ts
import type {
  // Result
  Ok, Err, Result,
  // Constraint パイプライン
  FieldState, Constraint,
  // バリデーター
  FieldResult, ValidationResult,
  // スキーマ
  JsonSchemaType, PropertySchema, ObjectSchema,
  ConditionalSchema, WhenCondition, WhenOp,
} from '@uuki/schemable-validator-client'
```

### `ObjectSchema`

`SchemaBuilder::toJsonSchema()` が出力するトップレベルの JSON Schema。

```ts
type ObjectSchema = {
  $schema: string
  type: 'object'
  properties: Record<string, PropertySchema>
  required?: readonly string[]
  'x-unmapped-fields'?: readonly string[]   // SV::file / SV::respect フィールド
  'x-when'?: readonly WhenCondition[]       // SchemaBuilder::when() の条件
  if?: ConditionalSchema['if']
  then?: ConditionalSchema['then']
  allOf?: readonly ConditionalSchema[]
}
```

### `PropertySchema`

`properties` 内の各フィールドのスキーマ。

```ts
type PropertySchema = {
  type?:      JsonSchemaType | readonly JsonSchemaType[]
  minLength?: number
  maxLength?: number
  format?:    'email' | 'uri' | 'date' | 'date-time' | 'time' | 'uuid' | 'ipv4' | 'ipv6' | 'hostname'
  pattern?:   string
  enum?:      readonly string[]
  minimum?:   number
  maximum?:   number
  items?:     PropertySchema   // 配列要素のスキーマ
  minItems?:  number
  maxItems?:  number
}
```

### `WhenCondition`

`x-when` 配列の1エントリ。

```ts
type WhenOp = '===' | '!==' | '>=' | '<=' | '>' | '<'

type WhenCondition =
  | { field: string; op: WhenOp; equals: unknown;      require: readonly string[] }
  | { field: string; op: WhenOp; equalsField: string;  require: readonly string[] }
```

### `FieldResult` / `ValidationResult`

`validateObject` の出力。PHP の `Validator::getResult()` の形状を踏襲し、スタック間の一貫性を保ちます。

```ts
type FieldResult = {
  value:    string | readonly string[]
  is_valid: boolean
  errors:   readonly string[] | null  // is_valid が true のとき null
}

type ValidationResult = Record<string, FieldResult>
```

### `FieldState` / `Constraint`

Constraint パイプラインの基本型。

```ts
type FieldState = {
  value:  string
  errors: readonly string[]
}

type Constraint = (state: FieldState) => FieldState
```
