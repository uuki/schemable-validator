// TypeScript types that mirror the JSON Schema output of SchemaBuilder::toJsonSchema().
// Keep in sync with packages/core/Schema/*.php field schema classes.

export type JsonSchemaType = 'string' | 'integer' | 'number' | 'boolean' | 'array' | 'null'

/** Per-field schema fragment, as emitted in the "properties" block. */
export type PropertySchema = {
  readonly type?: JsonSchemaType | readonly JsonSchemaType[]
  // string constraints
  readonly minLength?: number
  readonly maxLength?: number
  readonly format?: 'email' | 'uri' | 'date' | 'date-time' | 'time' | 'uuid' | 'ipv4' | 'ipv6' | 'hostname'
  readonly pattern?: string
  readonly enum?: readonly string[]
  // numeric constraints
  readonly minimum?: number
  readonly maximum?: number
  // array constraints
  readonly items?: PropertySchema
  readonly minItems?: number
  readonly maxItems?: number
  // Inline error messages keyed by JSON Schema keyword (AJV ajv-errors convention).
  // Resolution order: MessageDict > errorMessage > default.
  readonly errorMessage?: Readonly<Record<string, string>>
}

/** JSON Forms / RJSF UI Schema companion document produced by SchemaBuilder::toUiSchema(). */
export type UiSchemaControl = {
  readonly type: 'Control'
  readonly scope: string
  readonly label: string
}

export type UiSchema = {
  readonly type: 'VerticalLayout'
  readonly elements: readonly UiSchemaControl[]
}

/** Conditional requirement block (JSON Schema if/then). */
export type ConditionalSchema = {
  readonly if:   { readonly properties: Readonly<Record<string, { readonly const: unknown }>> }
  readonly then: { readonly required?: readonly string[] }
}

export type WhenOp = '===' | '!==' | '>=' | '<=' | '>' | '<'

/** One entry in the x-when extension array. */
export type WhenCondition =
  | { readonly field: string; readonly op: WhenOp; readonly equals: unknown; readonly require: readonly string[] }
  | { readonly field: string; readonly op: WhenOp; readonly equalsField: string; readonly require: readonly string[] }

/** Top-level JSON Schema object produced by SchemaBuilder. */
export type ObjectSchema = {
  readonly $schema: string
  readonly type: 'object'
  readonly properties: Readonly<Record<string, PropertySchema>>
  readonly required?: readonly string[]
  // Fields with no JSON Schema equivalent (e.g. SV::file, SV::respect).
  // Validated server-side only; clients pass these through untouched.
  readonly 'x-unmapped-fields'?: readonly string[]
  // Conditional requirements from SchemaBuilder::when()
  readonly if?:     ConditionalSchema['if']
  readonly then?:   ConditionalSchema['then']
  readonly allOf?:  readonly ConditionalSchema[]
  // Rich conditions (===, !==, field refs) — primary format for the TS client
  readonly 'x-when'?: readonly WhenCondition[]
}
