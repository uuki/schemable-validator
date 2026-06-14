/**
 * Shared foundation for Zod and Valibot schema builders.
 *
 * Exports:
 *   - evalWhenOp        — WhenOp comparator (pure function)
 *   - applyWhenConditions — iterates x-when conditions; adapter supplies the error callback
 *   - SvConfigBase<T>   — generic config base; adapters narrow T to their OnUnknown type
 *   - SchemaBuilderBase  — abstract class managing builder state + 5 setter methods
 *
 * The concrete build() logic and when-refiner wrappers live in each adapter because
 * the Zod and Valibot error APIs differ fundamentally.
 */
import type { ObjectSchema, WhenCondition, WhenOp } from '../schema.js'

// ── Shared utilities ──────────────────────────────────────────────────────────

/** Evaluate a single WhenOp comparison between two values. */
export function evalWhenOp(a: unknown, op: WhenOp, b: unknown): boolean {
  switch (op) {
    case '===': return a === b
    case '!==': return a !== b
    case '>=':  return (a as number) >= (b as number)
    case '<=':  return (a as number) <= (b as number)
    case '>':   return (a as number) >  (b as number)
    case '<':   return (a as number) <  (b as number)
  }
}

/**
 * Iterate x-when conditions and invoke addViolation for every required field
 * whose condition is met but the field value is missing/empty.
 *
 * The error-reporting format (Zod ctx.addIssue vs Valibot addIssue) is
 * adapter-specific — pass it as addViolation so the iteration logic stays here.
 */
export function applyWhenConditions(
  conditions: readonly WhenCondition[],
  data: Record<string, unknown>,
  addViolation: (key: string, value: unknown) => void,
): void {
  for (const cond of conditions) {
    const rhs = 'equalsField' in cond ? data[cond.equalsField] : cond.equals
    if (!evalWhenOp(data[cond.field], cond.op, rhs)) continue
    for (const key of cond.require) {
      const val = data[key]
      if (val === undefined || val === null || val === '') {
        addViolation(key, val)
      }
    }
  }
}

// ── Shared types ──────────────────────────────────────────────────────────────

/**
 * Configuration base for createSv() factories.
 * Each adapter re-exports this as `SvConfig` with its own OnUnknown type:
 *
 *   export type SvConfig = SvConfigBase<OnUnknown>
 *
 * Consumers import `SvConfig` from the adapter and never see SvConfigBase.
 */
export interface SvConfigBase<TOnUnknown> {
  /** Default onUnknown policy for every schema built by this factory. */
  onUnknown?: TOnUnknown
  /**
   * When true, run checkSchema() during build() and emit console.warn for
   * any unsupported fields. Useful during development. Default: false.
   */
  check?: boolean
}

// ── Abstract base builder ─────────────────────────────────────────────────────

/**
 * Manages shared state and provides the five setter methods common to both
 * Zod and Valibot builders.
 *
 * NOTE: Fields are `protected` (not ES `#private`) so concrete subclasses can
 * read them inside build() without going through getter indirection.
 * This is an internal hierarchy — nothing here is part of the public API.
 *
 * Type parameters:
 *   TOnUnknown   — adapter's OnUnknown type (Zod vs Valibot callback return differs)
 *   TExtField    — field type accepted by .extend()
 *   TRefiner     — synchronous refiner type
 *   TAsyncRefiner — async refiner type
 */
export abstract class SchemaBuilderBase<
  TOnUnknown,
  TExtField,
  TRefiner,
  TAsyncRefiner,
> {
  protected readonly json:    ObjectSchema
  protected readonly config:  SvConfigBase<TOnUnknown>
  protected perPolicy?:       TOnUnknown
  protected ext:              Record<string, TExtField> = {}
  protected applyWhen = false
  protected readonly syncRefiners:  TRefiner[]      = []
  protected readonly asyncRefiners: TAsyncRefiner[] = []

  constructor(json: ObjectSchema, config: SvConfigBase<TOnUnknown>) {
    this.json   = json
    this.config = config
  }

  /** Override the onUnknown policy for this schema only. */
  onUnknown(policy: TOnUnknown): this {
    this.perPolicy = policy
    return this
  }

  /**
   * Add or override fields absent from the JSON Schema.
   * Use for SV::file uploads, SV::respect fields, or any custom field.
   * In the Valibot adapter, async schemas (v.pipeAsync etc.) are auto-detected at build().
   */
  extend(fields: Record<string, TExtField>): this {
    this.ext = { ...this.ext, ...fields }
    return this
  }

  /** Auto-apply all x-when conditional requirements from the schema. */
  when(): this {
    this.applyWhen = true
    return this
  }

  /** Inject a synchronous cross-field validator. Logic lives outside this builder. */
  refine(fn: TRefiner): this {
    this.syncRefiners.push(fn)
    return this
  }

  /** Inject an async validator. The built schema requires parseAsync / safeParseAsync. */
  refineAsync(fn: TAsyncRefiner): this {
    this.asyncRefiners.push(fn)
    return this
  }

  /**
   * Produce the final schema.
   * Subclasses implement adapter-specific conversion, extension, and refiner wiring.
   * Return type is narrowed to the adapter's schema type in the concrete class.
   */
  abstract build(): unknown
}
