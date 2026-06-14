import { z } from 'zod'
import type { ObjectSchema, PropertySchema } from '../schema.js'

// ── Public types ──────────────────────────────────────────────────────────────

export type OnUnknown =
  | 'warn'
  | 'throw'
  | ((key: string, field: PropertySchema) => z.ZodTypeAny)

export interface ToZodSchemaOptions {
  onUnknown?: OnUnknown
}

export interface ZodSchemaReport {
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

// Bundlers (Vite, webpack) replace process.env.NODE_ENV with a string literal
// at build time, so this resolves statically in production bundles.
function defaultPolicy(): 'warn' | 'throw' {
  try {
    return process.env.NODE_ENV === 'production' ? 'throw' : 'warn'
  } catch {
    return 'warn'
  }
}

function propertyToZod(field: PropertySchema): z.ZodTypeAny {
  if (field.enum) {
    return z.enum(field.enum as [string, ...string[]])
  }

  // nullable: PHP's .nullable() emits type as ['string', 'null'] etc.
  if (Array.isArray(field.type)) {
    const bases = field.type.filter(t => t !== 'null')
    if (bases.length === 1) {
      return propertyToZod({ ...field, type: bases[0] }).nullable()
    }
    throw new UnsupportedField(
      field,
      `union type [${field.type.join(', ')}] is not supported`,
    )
  }

  if (field.type === 'string') {
    let s = z.string()
    if (field.minLength !== undefined) s = s.min(field.minLength)
    if (field.maxLength !== undefined) s = s.max(field.maxLength)
    switch (field.format) {
      case 'email':      s = s.email();                break
      case 'uri':        s = s.url();                  break
      case 'uuid':       s = s.uuid();                 break
      case 'ipv4':       s = s.ipv4();                 break
      case 'ipv6':       s = s.ipv6();                 break
      case 'date':       s = s.date();                 break
      case 'date-time':  s = s.datetime();             break
      case 'time':       s = s.time();                 break
      case undefined:    break
      default:
        throw new UnsupportedField(
          field,
          `format "${field.format}" has no built-in Zod equivalent`,
        )
    }
    if (field.pattern) s = s.regex(new RegExp(field.pattern))
    return s
  }

  if (field.type === 'integer') {
    let n = z.int()
    if (field.minimum !== undefined) n = n.min(field.minimum)
    if (field.maximum !== undefined) n = n.max(field.maximum)
    return n
  }

  if (field.type === 'number') {
    let n = z.number()
    if (field.minimum !== undefined) n = n.min(field.minimum)
    if (field.maximum !== undefined) n = n.max(field.maximum)
    return n
  }

  if (field.type === 'boolean') return z.boolean()

  if (field.type === 'array') {
    const itemSchema = field.items ? propertyToZod(field.items) : z.unknown()
    let arr = z.array(itemSchema)
    if (field.minItems !== undefined) arr = arr.min(field.minItems)
    if (field.maxItems !== undefined) arr = arr.max(field.maxItems)
    return arr
  }

  throw new UnsupportedField(
    field,
    `type "${field.type ?? 'unknown'}" is not supported`,
  )
}

// ── Exported API ──────────────────────────────────────────────────────────────

/**
 * Dry-run: check which fields in a schema are mappable to Zod without building
 * the schema. Does not throw — always returns a report.
 */
export function checkZodSchema(jsonSchema: ObjectSchema): ZodSchemaReport {
  const unmapped   = new Set(jsonSchema['x-unmapped-fields'] ?? [])
  const supported: string[] = []
  const unsupported: ZodSchemaReport['unsupported'] = []

  for (const [key, field] of Object.entries(jsonSchema.properties)) {
    if (unmapped.has(key)) continue
    try {
      propertyToZod(field)
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
 * Convert an ObjectSchema (JSON Schema) to a Zod v4 object schema.
 *
 * onUnknown behaviour (default resolves from process.env.NODE_ENV):
 *   'warn'     — console.warn and fall back to z.unknown()  [development]
 *   'throw'    — throw immediately                           [production]
 *   (fn)       — call fn(key, field) to supply a custom schema
 *
 * Limitations:
 * - x-unmapped-fields (SV::file etc.) are skipped; add them manually.
 * - format "hostname" has no Zod built-in; handle via onUnknown.
 * - x-when / if-then conditionals are not mapped.
 */
export function toZodSchema(
  jsonSchema: ObjectSchema,
  options: ToZodSchemaOptions = {},
): z.ZodObject<Record<string, z.ZodTypeAny>> {
  const policy   = options.onUnknown ?? defaultPolicy()
  const required = new Set(jsonSchema.required ?? [])
  const unmapped = new Set(jsonSchema['x-unmapped-fields'] ?? [])
  const shape: Record<string, z.ZodTypeAny> = {}

  for (const [key, field] of Object.entries(jsonSchema.properties)) {
    if (unmapped.has(key)) continue

    let zField: z.ZodTypeAny
    try {
      zField = propertyToZod(field)
    } catch (e) {
      if (!(e instanceof UnsupportedField)) throw e
      if (typeof policy === 'function') {
        zField = policy(key, field)
      } else if (policy === 'warn') {
        console.warn(`[schemable] toZodSchema: field "${key}": ${e.reason}`)
        zField = z.unknown()
      } else {
        throw e
      }
    }

    shape[key] = required.has(key) ? zField : zField.optional()
  }

  return z.object(shape)
}
