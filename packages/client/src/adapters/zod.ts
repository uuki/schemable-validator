import { z } from 'zod'
import type { ObjectSchema, PropertySchema, WhenEntry } from '../schema.js'
import { applyWhenConditions, SchemaBuilderBase, type SvConfigBase } from './builder.js'

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

/** Synchronous validator injected via sv().refine(). Lives outside this builder. */
export type ZodRefiner = (
  data: Record<string, unknown>,
  ctx:  z.RefinementCtx,
) => void

/** Async validator injected via sv().refineAsync(). Requires schema.parseAsync(). */
export type ZodAsyncRefiner = (
  data: Record<string, unknown>,
  ctx:  z.RefinementCtx,
) => Promise<void>

/**
 * Shared configuration for createSv().
 * Re-exported alias of SvConfigBase<OnUnknown> — consumers never see SvConfigBase.
 */
export type SvConfig = SvConfigBase<OnUnknown>

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

// Bundlers replace process.env.NODE_ENV with a string literal at build time.
function defaultPolicy(): 'warn' | 'throw' {
  try {
    return process.env.NODE_ENV === 'production' ? 'throw' : 'warn'
  } catch {
    return 'warn'
  }
}

function propertyToZod(field: PropertySchema): z.ZodTypeAny {
  if (field.enum) return z.enum(field.enum as [string, ...string[]])

  if (Array.isArray(field.type)) {
    const bases = field.type.filter(t => t !== 'null')
    if (bases.length === 1) return propertyToZod({ ...field, type: bases[0] }).nullable()
    throw new UnsupportedField(field, `union type [${field.type.join(', ')}] is not supported`)
  }

  if (field.type === 'string') {
    let s = z.string()
    if (field.minLength !== undefined) s = s.min(field.minLength)
    if (field.maxLength !== undefined) s = s.max(field.maxLength)
    // Use Zod v4 standalone validators via .check() — avoids deprecated ZodString chain methods
    switch (field.format) {
      case 'email':     s = s.check(z.email());         break
      case 'uri':       s = s.check(z.url());            break
      case 'uuid':      s = s.check(z.uuid());           break
      case 'ipv4':      s = s.check(z.ipv4());           break
      case 'ipv6':      s = s.check(z.ipv6());           break
      case 'date':      s = s.check(z.iso.date());       break
      case 'date-time': s = s.check(z.iso.datetime());   break
      case 'time':      s = s.check(z.iso.time());       break
      case undefined:   break
      default: throw new UnsupportedField(field, `format "${field.format}" has no built-in Zod equivalent`)
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

  throw new UnsupportedField(field, `type "${field.type ?? 'unknown'}" is not supported`)
}

/** Wrap the shared when-condition iterator with Zod's error API. */
function buildWhenRefiner(conditions: readonly WhenEntry[]): ZodRefiner {
  return (data, ctx) => {
    applyWhenConditions(conditions, data, (key) => {
      ctx.addIssue({ code: 'custom', path: [key], message: 'Required' })
    })
  }
}

// ── Builder ───────────────────────────────────────────────────────────────────

type ZodObjectBase  = z.ZodObject<Record<string, z.ZodTypeAny>>
// ZodEffects was renamed in Zod v4; use any to avoid coupling to an internal type name.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type ZodBuiltSchema = ZodObjectBase | any

class ZodSchemaBuilder extends SchemaBuilderBase<OnUnknown, z.ZodTypeAny, ZodRefiner, ZodAsyncRefiner> {
  constructor(json: ObjectSchema, config: SvConfig) {
    super(json, config)
  }

  build(): ZodBuiltSchema {
    const policy = this.perPolicy ?? this.config.onUnknown

    if (this.config.check) {
      const { unsupported } = checkZodSchema(this.json)
      if (unsupported.length) {
        console.warn('[schemable] sv.build(): unsupported fields detected:', unsupported)
      }
    }

    // Phase 1: base schema from JSON Schema
    let base = toZodSchema(this.json, policy !== undefined ? { onUnknown: policy } : {})

    // Phase 2: extend with additional fields (base remains a ZodObject for .extend())
    if (Object.keys(this.ext).length > 0) base = base.extend(this.ext)

    // Phase 3: build refiner chain (when → sync → async)
    const chain: ZodRefiner[] = []
    if (this.applyWhen && this.json['x-when']?.length) {
      chain.push(buildWhenRefiner(this.json['x-when']))
    }
    chain.push(...this.syncRefiners)

    if (chain.length === 0 && this.asyncRefiners.length === 0) return base

    if (this.asyncRefiners.length === 0) {
      // Pure sync path — no async overhead
      return base.superRefine((data, ctx) => {
        for (const fn of chain) fn(data as Record<string, unknown>, ctx)
      })
    }

    // Mixed or async-only — run all refiners sequentially
    const asyncChain = this.asyncRefiners
    return base.superRefine(async (data, ctx) => {
      const d = data as Record<string, unknown>
      for (const fn of chain) fn(d, ctx)
      for (const fn of asyncChain) await fn(d, ctx)
    })
  }
}

// ── Exported API ──────────────────────────────────────────────────────────────

/**
 * Dry-run: report which fields are and are not mappable to Zod.
 * Does not throw — always returns a report object.
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
 * Convert an ObjectSchema to a Zod v4 object schema.
 * Prefer sv() for fluent construction with when/refine/extend.
 */
export function toZodSchema(
  jsonSchema: ObjectSchema,
  options: ToZodSchemaOptions = {},
): ZodObjectBase {
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

/**
 * Create a pre-configured schema builder factory.
 * Use this at the application level to share onUnknown policy across forms.
 *
 * @example
 * const sv = createSv({ onUnknown: myFallback, check: true })
 * const schema = sv(jsonSchema).when().refine(myRule).build()
 */
export function createSv(config: SvConfig = {}): (json: ObjectSchema) => ZodSchemaBuilder {
  return (json) => new ZodSchemaBuilder(json, config)
}

/**
 * Fluent schema builder. Shorthand for createSv()(jsonSchema).
 *
 * Chain: .onUnknown() .extend() .when() .refine() .refineAsync() .build()
 * Call order does not matter — build() always applies phases in the correct order.
 *
 * @example
 * // Sync
 * const schema = sv(jsonSchema).when().refine(checkDates).build()
 * const result = schema.safeParse(data)
 *
 * // Async (requires parseAsync)
 * const schema = sv(jsonSchema).when().refineAsync(checkAvailability).build()
 * const result = await schema.parseAsync(data)
 */
export const sv: (json: ObjectSchema) => ZodSchemaBuilder = createSv()
