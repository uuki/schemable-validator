// TypeScript types that mirror the JSON Schema output of SchemaBuilder::toJsonSchema().
// Keep in sync with packages/core/Schema/*.php field schema classes.

export type JsonSchemaType = 'string' | 'integer' | 'number' | 'boolean' | 'null'

/** Per-field schema fragment, as emitted in the "properties" block. */
export type PropertySchema = {
  readonly type?: JsonSchemaType | readonly JsonSchemaType[]
  // string constraints
  readonly minLength?: number
  readonly maxLength?: number
  readonly format?: 'email' | 'uri'
  readonly pattern?: string
  readonly enum?: readonly string[]
  // numeric constraints
  readonly minimum?: number
  readonly maximum?: number
}

/** Top-level JSON Schema object produced by SchemaBuilder. */
export type ObjectSchema = {
  readonly $schema: string
  readonly type: 'object'
  readonly properties: Readonly<Record<string, PropertySchema>>
  readonly required?: readonly string[]
  // Fields with no JSON Schema equivalent (e.g. SV::file, SV::respect).
  // Validated server-side only; clients pass these through untouched.
  readonly 'x-unmapped-fields'?: readonly string[]
}
