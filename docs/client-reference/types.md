# Type Reference

All types are re-exported from the main entry.

```ts
import type {
  // Result
  Ok, Err, Result,
  // Constraint pipeline
  FieldState, Constraint,
  // Validator
  FieldResult, ValidationResult,
  // Schema
  JsonSchemaType, PropertySchema, ObjectSchema,
  ConditionalSchema, WhenEntry,
  // JSONLogic
  JLCondition, JLValue, JLVar,
  // UI Schema
  UiSchema, UiSchemaControl,
} from '@uuki/schemable-validator-client'
```

---

## `ObjectSchema`

Top-level JSON Schema from `SchemaBuilder::toJsonSchema()`.

```ts
type ObjectSchema = {
  $schema: string
  type: 'object'
  properties: Readonly<Record<string, PropertySchema>>
  required?: readonly string[]
  'x-unmapped-fields'?: readonly string[]   // SV::file / RespectRules::rule() fields
  'x-custom-fields'?:   readonly string[]   // SchemaBuilder::customFields() — BE-only logic
  'x-when'?: readonly WhenEntry[]           // SchemaBuilder::when() conditions (JSONLogic)
  if?: ConditionalSchema['if']
  then?: ConditionalSchema['then']
  allOf?: readonly ConditionalSchema[]
}
```

---

## `PropertySchema`

Per-field fragment inside `properties`.

```ts
type PropertySchema = {
  type?:      JsonSchemaType | readonly JsonSchemaType[]
  minLength?: number
  maxLength?: number
  format?:    'email' | 'uri' | 'date' | 'date-time' | 'time' | 'uuid' | 'ipv4' | 'ipv6' | 'hostname'
  pattern?:   string
  enum?:      readonly string[]
  'x-transform'?: readonly string[]                // value transforms applied before validation (trim, toLowerCase, toUpperCase)
  minimum?:   number
  maximum?:   number
  items?:     PropertySchema   // array element schema
  minItems?:  number
  maxItems?:  number
  errorMessage?: Readonly<Record<string, string>>  // inline error messages keyed by JSON Schema keyword
}
```

---

## `WhenEntry`

One entry in the `x-when` array. Uses a JSONLogic condition instead of the legacy operator format.

```ts
type WhenEntry = {
  readonly condition: JLCondition
  readonly require: readonly string[]
}
```

---

## JSONLogic Types

Minimal JSONLogic subset used by `x-when` conditions and `applyJsonLogic`.

```ts
type JLVar = { readonly var: string }

type JLValue = string | number | boolean | null | JLVar

type JLCondition =
  | { readonly '===': readonly [JLValue, JLValue] }
  | { readonly '!==': readonly [JLValue, JLValue] }
  | { readonly '>=':  readonly [JLValue, JLValue] }
  | { readonly '<=':  readonly [JLValue, JLValue] }
  | { readonly '>':   readonly [JLValue, JLValue] }
  | { readonly '<':   readonly [JLValue, JLValue] }
  | { readonly and:   readonly JLCondition[] }
  | { readonly or:    readonly JLCondition[] }
```

---

## `UiSchema` / `UiSchemaControl`

JSON Forms / RJSF UI Schema companion document produced by `SchemaBuilder::toUiSchema()`.

```ts
type UiSchemaControl = {
  readonly type: 'Control'
  readonly scope: string
  readonly label: string
}

type UiSchema = {
  readonly type: 'VerticalLayout'
  readonly elements: readonly UiSchemaControl[]
}
```

---

## `FieldResult` / `ValidationResult`

Output of `validateObject`. Mirrors the PHP `Validator::getResult()` shape for cross-stack consistency.

```ts
type FieldResult = {
  value:    string | readonly string[]
  is_valid: boolean
  errors:   readonly string[] | null  // null when is_valid is true
}

type ValidationResult = Record<string, FieldResult>
```

---

## `FieldState` / `Constraint`

Primitives for the constraint pipeline.

```ts
type FieldState = {
  value:  string
  errors: readonly string[]
}

type Constraint = (state: FieldState) => FieldState
```

---

## `Ok` / `Err` / `Result`

Result union type.

```ts
type Ok<A>  = { readonly _tag: 'Ok';  readonly value: A }
type Err<E> = { readonly _tag: 'Err'; readonly error: E }
type Result<A, E> = Ok<A> | Err<E>
```
