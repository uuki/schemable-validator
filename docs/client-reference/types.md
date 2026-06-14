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
  ConditionalSchema, WhenCondition, WhenOp,
} from '@uuki/schemable-validator-client'
```

---

## `ObjectSchema`

Top-level JSON Schema from `SchemaBuilder::toJsonSchema()`.

```ts
type ObjectSchema = {
  $schema: string
  type: 'object'
  properties: Record<string, PropertySchema>
  required?: readonly string[]
  'x-unmapped-fields'?: readonly string[]   // SV::file / SV::respect fields
  'x-when'?: readonly WhenCondition[]       // SchemaBuilder::when() conditions
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
  minimum?:   number
  maximum?:   number
  items?:     PropertySchema   // array element schema
  minItems?:  number
  maxItems?:  number
}
```

---

## `WhenCondition`

One entry in the `x-when` array.

```ts
type WhenOp = '===' | '!==' | '>=' | '<=' | '>' | '<'

type WhenCondition =
  | { field: string; op: WhenOp; equals: unknown;      require: readonly string[] }
  | { field: string; op: WhenOp; equalsField: string;  require: readonly string[] }
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
