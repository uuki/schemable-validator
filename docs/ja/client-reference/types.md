# 型リファレンス

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

---

## `ObjectSchema`

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

---

## `PropertySchema`

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

---

## `WhenCondition`

`x-when` 配列の1エントリ。

```ts
type WhenOp = '===' | '!==' | '>=' | '<=' | '>' | '<'

type WhenCondition =
  | { field: string; op: WhenOp; equals: unknown;      require: readonly string[] }
  | { field: string; op: WhenOp; equalsField: string;  require: readonly string[] }
```

---

## `FieldResult` / `ValidationResult`

`validateObject` の出力。PHP の `Validator::getResult()` の形状を踏襲し、スタック間の一貫性を保ちます。

```ts
type FieldResult = {
  value:    string | readonly string[]
  is_valid: boolean
  errors:   readonly string[] | null  // is_valid が true のとき null
}

type ValidationResult = Record<string, FieldResult>
```

---

## `FieldState` / `Constraint`

Constraint パイプラインの基本型。

```ts
type FieldState = {
  value:  string
  errors: readonly string[]
}

type Constraint = (state: FieldState) => FieldState
```

---

## `Ok` / `Err` / `Result`

Result ユニオン型。

```ts
type Ok<A>  = { readonly _tag: 'Ok';  readonly value: A }
type Err<E> = { readonly _tag: 'Err'; readonly error: E }
type Result<A, E> = Ok<A> | Err<E>
```
