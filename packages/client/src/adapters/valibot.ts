import * as v from 'valibot'
import type { ObjectSchema, PropertySchema } from '../schema.js'

// ── Public types ──────────────────────────────────────────────────────────────

export type OnUnknown =
  | 'warn'
  | 'throw'
  | ((key: string, field: PropertySchema) => v.GenericSchema)

export interface ToValibotSchemaOptions {
  onUnknown?: OnUnknown
}

export interface ValibotSchemaReport {
  supported:   string[]
  unsupported: { key: string; field: PropertySchema; reason: string }[]
}

// ── Internal ──────────────────────────────────────────────────────────────────

class UnsupportedField extends Error {
  constructor(
    public readonly field: PropertySchema,
    public readonly reason: string,
  ) {
    super(reason)
    this.name = 'UnsupportedField'
  }
}

function defaultPolicy(): 'warn' | 'throw' {
  try {
    return process.env.NODE_ENV === 'production' ? 'throw' : 'warn'
  } catch {
    return 'warn'
  }
}

function propertyToValibot(field: PropertySchema): v.GenericSchema {
  if (field.enum) {
    return v.picklist(field.enum as [string, ...string[]])
  }

  // nullable: PHP's .nullable() emits type as ['string', 'null'] etc.
  if (Array.isArray(field.type)) {
    const bases = field.type.filter(t => t !== 'null')
    if (bases.length === 1) {
      return v.nullable(propertyToValibot({ ...field, type: bases[0] }))
    }
    throw new UnsupportedField(
      field,
      `union type [${field.type.join(', ')}] is not supported`,
    )
  }

  if (field.type === 'string') {
    type StringAction = v.PipeItem<string, string, v.BaseIssue<unknown>>
    const actions: StringAction[] = []

    switch (field.format) {
      case 'email':      actions.push(v.email());       break
      case 'uri':        actions.push(v.url());          break
      case 'uuid':       actions.push(v.uuid());         break
      case 'ipv4':       actions.push(v.ipv4());         break
      case 'ipv6':       actions.push(v.ipv6());         break
      case 'date':       actions.push(v.isoDate());        break
      // isoTimestamp accepts full ISO 8601 with timezone (Z, +offset)
      case 'date-time':  actions.push(v.isoTimestamp());  break
      // isoTimeSecond accepts HH:MM:SS; isoTime only accepts HH:MM
      case 'time':       actions.push(v.isoTimeSecond()); break
      case undefined:    break
      default:
        throw new UnsupportedField(
          field,
          `format "${field.format}" has no built-in Valibot equivalent`,
        )
    }

    if (field.pattern)              actions.push(v.regex(new RegExp(field.pattern)))
    if (field.minLength !== undefined) actions.push(v.minLength(field.minLength))
    if (field.maxLength !== undefined) actions.push(v.maxLength(field.maxLength))

    return actions.length ? v.pipe(v.string(), ...actions) : v.string()
  }

  if (field.type === 'integer') {
    type NumAction = v.PipeItem<number, number, v.BaseIssue<unknown>>
    const actions: NumAction[] = [v.integer()]
    if (field.minimum !== undefined) actions.push(v.minValue(field.minimum))
    if (field.maximum !== undefined) actions.push(v.maxValue(field.maximum))
    return v.pipe(v.number(), ...actions)
  }

  if (field.type === 'number') {
    type NumAction = v.PipeItem<number, number, v.BaseIssue<unknown>>
    const actions: NumAction[] = []
    if (field.minimum !== undefined) actions.push(v.minValue(field.minimum))
    if (field.maximum !== undefined) actions.push(v.maxValue(field.maximum))
    return actions.length ? v.pipe(v.number(), ...actions) : v.number()
  }

  if (field.type === 'boolean') return v.boolean()

  if (field.type === 'array') {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const itemSchema = (field.items ? propertyToValibot(field.items) : v.unknown()) as any
    const arrBase    = v.array(itemSchema)
    type ArrAction   = v.PipeItem<unknown[], unknown[], v.BaseIssue<unknown>>
    const actions: ArrAction[] = []
    if (field.minItems !== undefined) actions.push(v.minLength(field.minItems))
    if (field.maxItems !== undefined) actions.push(v.maxLength(field.maxItems))
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    return actions.length ? v.pipe(arrBase, ...(actions as any)) : arrBase
  }

  throw new UnsupportedField(
    field,
    `type "${field.type ?? 'unknown'}" is not supported`,
  )
}

// ── Exported API ──────────────────────────────────────────────────────────────

/**
 * Dry-run: check which fields in a schema are mappable to Valibot without
 * building the schema. Does not throw — always returns a report.
 */
export function checkValibotSchema(jsonSchema: ObjectSchema): ValibotSchemaReport {
  const unmapped   = new Set(jsonSchema['x-unmapped-fields'] ?? [])
  const supported: string[] = []
  const unsupported: ValibotSchemaReport['unsupported'] = []

  for (const [key, field] of Object.entries(jsonSchema.properties)) {
    if (unmapped.has(key)) continue
    try {
      propertyToValibot(field)
      supported.push(key)
    } catch (e) {
      if (e instanceof UnsupportedField) {
        unsupported.push({ key, field, reason: e.reason })
      } else {
        throw e
      }
    }
  }

  return { supported, unsupported }
}

/**
 * Convert an ObjectSchema (JSON Schema) to a Valibot v1 object schema.
 *
 * onUnknown behaviour (default resolves from process.env.NODE_ENV):
 *   'warn'     — console.warn and fall back to v.unknown()  [development]
 *   'throw'    — throw immediately                           [production]
 *   (fn)       — call fn(key, field) to supply a custom schema
 *
 * Limitations:
 * - x-unmapped-fields (SV::file etc.) are skipped; add them manually.
 * - format "hostname" has no Valibot built-in; handle via onUnknown.
 * - x-when / if-then conditionals are not mapped.
 */
export function toValibotSchema(
  jsonSchema: ObjectSchema,
  options: ToValibotSchemaOptions = {},
) {
  const policy   = options.onUnknown ?? defaultPolicy()
  const required = new Set(jsonSchema.required ?? [])
  const unmapped = new Set(jsonSchema['x-unmapped-fields'] ?? [])
  const shape: Record<string, v.GenericSchema> = {}

  for (const [key, field] of Object.entries(jsonSchema.properties)) {
    if (unmapped.has(key)) continue

    let vField: v.GenericSchema
    try {
      vField = propertyToValibot(field)
    } catch (e) {
      if (!(e instanceof UnsupportedField)) throw e
      if (typeof policy === 'function') {
        vField = policy(key, field)
      } else if (policy === 'warn') {
        console.warn(`[schemable] toValibotSchema: field "${key}": ${e.reason}`)
        vField = v.unknown()
      } else {
        throw e
      }
    }

    shape[key] = required.has(key) ? vField : v.optional(vField)
  }

  return v.object(shape)
}
