import { ok, err, type Result } from './result.js'
import { constraintsFromSchema, applyTransform, type FieldState } from './constraint.js'
import type { ObjectSchema, PropertySchema, ConditionalSchema, WhenEntry } from './schema.js'
import { applyJsonLogic } from './jsonLogic.js'
import { DEFAULT_MESSAGES } from './messages.js'

// ── Output types ─────────────────────────────────────────────────────────────
// Mirror the shape of PHP Validator::getResult() for cross-stack consistency.

export type FieldResult = {
  readonly value: string | readonly string[]
  readonly is_valid: boolean
  readonly errors: readonly string[] | null
}

export type ValidationResult = Readonly<Record<string, FieldResult>>

// ── Field validation ──────────────────────────────────────────────────────────

const validateField = (
  value: string,
  schema: PropertySchema,
  required: boolean,
): Result<string, readonly string[]> => {
  const transformed = schema['x-transform']?.length
    ? applyTransform(value, schema['x-transform'])
    : value

  const isEmpty = transformed === ''

  if (required && isEmpty) return err([DEFAULT_MESSAGES.required])
  if (isEmpty) return ok(transformed) // optional + empty → always valid

  const initial: FieldState = { value: transformed, errors: [] }
  const final = constraintsFromSchema(schema)(initial)

  return final.errors.length === 0 ? ok(transformed) : err(final.errors)
}

const validateArrayField = (
  values: readonly string[],
  schema: PropertySchema,
  required: boolean,
): Result<readonly string[], readonly string[]> => {
  if (required && values.length === 0) return err([DEFAULT_MESSAGES.required])
  if (values.length === 0) return ok(values)

  const itemSchema = schema.items
  if (!itemSchema) return ok(values)

  const errors: string[] = []
  for (const v of values) {
    const initial: FieldState = { value: v, errors: [] }
    const final = constraintsFromSchema(itemSchema)(initial)
    errors.push(...final.errors)
  }
  if (schema.minItems !== undefined && values.length < schema.minItems) {
    errors.push(`must have at least ${schema.minItems} item${schema.minItems !== 1 ? 's' : ''}`)
  }
  if (schema.maxItems !== undefined && values.length > schema.maxItems) {
    errors.push(`must have no more than ${schema.maxItems} item${schema.maxItems !== 1 ? 's' : ''}`)
  }
  return errors.length === 0 ? ok(values) : err(errors)
}

const toFieldResult = (
  rawValue: string | readonly string[],
  result: Result<string | readonly string[], readonly string[]>,
): FieldResult =>
  result._tag === 'Ok'
    ? { value: result.value, is_valid: true, errors: null }
    : { value: rawValue, is_valid: false, errors: result.error }

// ── Conditional evaluation ────────────────────────────────────────────────────

const resolveString = (
  value: string | readonly string[] | undefined,
): string =>
  Array.isArray(value) ? (value as readonly string[]).join(',') : (value as string) ?? ''

const evaluateWhenEntry = (
  cond: WhenEntry,
  data: Readonly<Record<string, string | readonly string[]>>,
  result: Record<string, FieldResult>,
): void => {
  if (!applyJsonLogic(cond.condition, data as Record<string, unknown>)) return
  for (const name of cond.require) {
    const val = data[name] ?? ''
    const isEmpty = Array.isArray(val) ? (val as readonly string[]).length === 0 : val === ''
    if (isEmpty) {
      result[name] = { value: val as string, is_valid: false, errors: [DEFAULT_MESSAGES.required] }
    }
  }
}

/** Legacy: evaluate a standard JSON Schema if/then block (literal === only). */
const evaluateConditional = (
  cond: ConditionalSchema,
  data: Readonly<Record<string, string | readonly string[]>>,
  result: Record<string, FieldResult>,
): void => {
  const [entry] = Object.entries(cond.if.properties)
  if (!entry) return
  const [field, matcher] = entry
  const matches = resolveString(data[field]) === String(matcher.const)
  if (!matches) return
  for (const name of cond.then.required ?? []) {
    const val = data[name] ?? ''
    const isEmpty = Array.isArray(val) ? (val as readonly string[]).length === 0 : val === ''
    if (isEmpty) {
      result[name] = { value: val as string, is_valid: false, errors: [DEFAULT_MESSAGES.required] }
    }
  }
}

// ── Object validation ─────────────────────────────────────────────────────────

export const validateObject = (
  data: Readonly<Record<string, string | readonly string[]>>,
  schema: ObjectSchema,
): ValidationResult => {
  const required = schema.required ?? []
  const unmapped = schema['x-unmapped-fields'] ?? []
  const result: Record<string, FieldResult> = {}

  for (const [name, fieldSchema] of Object.entries(schema.properties)) {
    const raw = data[name]
    if (fieldSchema.items !== undefined || fieldSchema.type === 'array') {
      const values = Array.isArray(raw) ? (raw as readonly string[]) : raw !== undefined ? [raw as string] : []
      result[name] = toFieldResult(values, validateArrayField(values, fieldSchema, required.includes(name)))
    } else {
      const value = Array.isArray(raw) ? (raw as readonly string[]).join(',') : (raw as string) ?? ''
      result[name] = toFieldResult(value, validateField(value, fieldSchema, required.includes(name)))
    }
  }

  // Unmapped fields cannot be validated client-side — pass through as valid.
  for (const name of unmapped) {
    const val = data[name] ?? ''
    result[name] = { value: val as string, is_valid: true, errors: null }
  }

  // Evaluate conditional requirements from SchemaBuilder::when()
  // x-when is the primary format (supports ===, !==, field refs).
  // Fall back to standard if/then / allOf for schemas without x-when.
  if (schema['x-when'] !== undefined) {
    for (const cond of schema['x-when']) {
      evaluateWhenEntry(cond, data, result)
    }
  } else {
    if (schema.if && schema.then) {
      evaluateConditional({ if: schema.if, then: schema.then }, data, result)
    }
    for (const cond of schema.allOf ?? []) {
      evaluateConditional(cond, data, result)
    }
  }

  return result
}

// ── Result helpers ────────────────────────────────────────────────────────────

export const isAllValid = (result: ValidationResult): boolean =>
  Object.values(result).every((f) => f.is_valid)

/** Return only the fields that failed, keyed by name. */
export const extractErrors = (
  result: ValidationResult,
): Readonly<Record<string, readonly string[]>> => {
  const errors: Record<string, readonly string[]> = {}
  for (const [name, field] of Object.entries(result)) {
    if (!field.is_valid && field.errors !== null) {
      errors[name] = field.errors
    }
  }
  return errors
}
