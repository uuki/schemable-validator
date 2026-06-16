import * as v from 'valibot'
import type { ObjectSchema, PropertySchema, WhenEntry } from '../schema.js'
import { applyWhenConditions, SchemaBuilderBase, type SvConfigBase } from './builder.js'

// ── Public types ──────────────────────────────────────────────────────────────

export type OnUnknown =
  | 'warn'
  | 'throw'
  | ((key: string, field: PropertySchema) => v.GenericSchema | v.GenericSchemaAsync)

export interface ToValibotSchemaOptions {
  onUnknown?: OnUnknown
}

export interface ValibotSchemaReport {
  supported:   string[]
  unsupported: { key: string; field: PropertySchema; reason: string }[]
}

/** Minimal rawCheck context shape. Structurally compatible with Valibot's internal type. */
type RawCheckCtx = {
  readonly dataset: { readonly typed: boolean; readonly value: unknown }
  readonly addIssue: (issue: {
    message: string
    path?: ReadonlyArray<{ key: unknown; type: string; origin: string; input: unknown; value: unknown }>
  }) => void
}

/** Synchronous validator injected via sv().refine(). Lives outside this builder. */
export type ValibotRefiner = (context: RawCheckCtx) => void

/** Async validator injected via sv().refineAsync(). Requires v.safeParseAsync(). */
export type ValibotAsyncRefiner = (context: RawCheckCtx) => Promise<void>

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

function propertyToValibot(field: PropertySchema): v.GenericSchema {
  if (field.enum) return v.picklist(field.enum as [string, ...string[]])

  // nullable: PHP's .nullable() emits type as ['string', 'null'] etc.
  if (Array.isArray(field.type)) {
    const bases = field.type.filter(t => t !== 'null')
    if (bases.length === 1) return v.nullable(propertyToValibot({ ...field, type: bases[0] }))
    throw new UnsupportedField(field, `union type [${field.type.join(', ')}] is not supported`)
  }

  if (field.type === 'string') {
    type StringAction = v.PipeItem<string, string, v.BaseIssue<unknown>>
    const actions: StringAction[] = []

    switch (field.format) {
      case 'email':     actions.push(v.email());        break
      case 'uri':       actions.push(v.url());           break
      case 'uuid':      actions.push(v.uuid());          break
      case 'ipv4':      actions.push(v.ipv4());          break
      case 'ipv6':      actions.push(v.ipv6());          break
      case 'date':      actions.push(v.isoDate());       break
      // isoTimestamp accepts full ISO 8601 with timezone (Z, +offset)
      case 'date-time': actions.push(v.isoTimestamp());  break
      // isoTimeSecond accepts HH:MM:SS; isoTime only accepts HH:MM
      case 'time':      actions.push(v.isoTimeSecond()); break
      case undefined:   break
      default: throw new UnsupportedField(field, `format "${field.format}" has no built-in Valibot equivalent`)
    }

    if (field.pattern)               actions.push(v.regex(new RegExp(field.pattern)))
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

  throw new UnsupportedField(field, `type "${field.type ?? 'unknown'}" is not supported`)
}

/** Wrap the shared when-condition iterator with Valibot's rawCheck error API. */
function buildWhenChecker(conditions: readonly WhenEntry[]): ValibotRefiner {
  return ({ dataset, addIssue }) => {
    if (!dataset.typed) return
    const data = dataset.value as Record<string, unknown>
    applyWhenConditions(conditions, data, (key, val) => {
      addIssue({
        message: 'Required',
        path: [{ key, type: 'object', origin: 'value', input: data, value: val }],
      })
    })
  }
}

// ── Builder ───────────────────────────────────────────────────────────────────

type ValibotExtField = v.GenericSchema | v.GenericSchemaAsync

class ValibotSchemaBuilder extends SchemaBuilderBase<OnUnknown, ValibotExtField, ValibotRefiner, ValibotAsyncRefiner> {
  constructor(json: ObjectSchema, config: SvConfig) {
    super(json, config)
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  build(): any {
    const policy = this.perPolicy ?? this.config.onUnknown

    if (this.config.check) {
      const { unsupported } = checkValibotSchema(this.json)
      if (unsupported.length) {
        console.warn('[schemable] sv.build(): unsupported fields detected:', unsupported)
      }
      const customFields = this.json['x-custom-fields'] ?? []
      if (customFields.length > 0 && this.syncRefiners.length === 0 && this.asyncRefiners.length === 0) {
        console.warn('[schemable] sv.build(): x-custom-fields declared but no .refine()/.refineAsync() registered:', customFields)
      }
    }

    // Phase 1 + 2: merge base entries with extension fields
    const base       = toValibotSchema(this.json, policy !== undefined ? { onUnknown: policy } : {})
    const allEntries = { ...base.entries, ...this.ext }

    // Detect async entries to auto-select objectAsync / pipeAsync
    const hasAsyncEntries  = Object.values(allEntries).some(s => (s as { async?: boolean }).async === true)
    const hasAsyncRefiners = this.asyncRefiners.length > 0
    const isAsync          = hasAsyncEntries || hasAsyncRefiners

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const obj = isAsync ? v.objectAsync(allEntries as any) : v.object(allEntries as any)

    // Phase 3: build check chain (when → sync → async)
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const syncChecks: any[] = []
    if (this.applyWhen && this.json['x-when']?.length) {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      syncChecks.push(v.rawCheck(buildWhenChecker(this.json['x-when']) as any))
    }
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    for (const fn of this.syncRefiners) syncChecks.push(v.rawCheck(fn as any))
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const asyncChecks = this.asyncRefiners.map(fn => v.rawCheckAsync(fn as any))

    if (syncChecks.length === 0 && asyncChecks.length === 0) return obj

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    if (!isAsync) return v.pipe(obj as any, ...syncChecks)
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    return v.pipeAsync(obj as any, ...syncChecks, ...asyncChecks)
  }
}

// ── Exported API ──────────────────────────────────────────────────────────────

/**
 * Dry-run: report which fields are and are not mappable to Valibot.
 * Does not throw — always returns a report object.
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
 * Prefer sv() for fluent construction with when/refine/extend.
 *
 * onUnknown behaviour (default resolves from process.env.NODE_ENV):
 *   'warn'  — console.warn and fall back to v.unknown()  [development]
 *   'throw' — throw immediately                           [production]
 *   (fn)    — call fn(key, field) to supply a custom schema
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
        vField = policy(key, field) as v.GenericSchema
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

/**
 * Create a pre-configured schema builder factory.
 * Use this at the application level to share onUnknown policy across forms.
 *
 * @example
 * const sv = createSv({ onUnknown: myFallback, check: true })
 * const schema = sv(jsonSchema).when().refine(myRule).build()
 * const result = v.safeParse(schema, data)
 */
export function createSv(config: SvConfig = {}): (json: ObjectSchema) => ValibotSchemaBuilder {
  return (json) => new ValibotSchemaBuilder(json, config)
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
 * const result = v.safeParse(schema, data)
 *
 * // Async (requires safeParseAsync)
 * const schema = sv(jsonSchema).when().refineAsync(checkAvailability).build()
 * const result = await v.safeParseAsync(schema, data)
 */
export const sv: (json: ObjectSchema) => ValibotSchemaBuilder = createSv()
