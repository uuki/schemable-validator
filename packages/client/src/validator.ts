import { ok, err, type Result } from './result.js'
import { constraintsFromSchema, type FieldState } from './constraint.js'
import type { ObjectSchema, PropertySchema } from './schema.js'

// ── Output types ─────────────────────────────────────────────────────────────
// Mirror the shape of PHP Validator::getResult() for cross-stack consistency.

export type FieldResult = {
  readonly value: string
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
  const isEmpty = value === ''

  if (required && isEmpty) return err(['is required'])
  if (isEmpty) return ok(value) // optional + empty → always valid

  const initial: FieldState = { value, errors: [] }
  const final = constraintsFromSchema(schema)(initial)

  return final.errors.length === 0 ? ok(value) : err(final.errors)
}

const toFieldResult = (value: string, result: Result<string, readonly string[]>): FieldResult =>
  result._tag === 'Ok'
    ? { value, is_valid: true, errors: null }
    : { value, is_valid: false, errors: result.error }

// ── Object validation ─────────────────────────────────────────────────────────

export const validateObject = (
  data: Readonly<Record<string, string>>,
  schema: ObjectSchema,
): ValidationResult => {
  const required = schema.required ?? []
  const unmapped = schema['x-unmapped-fields'] ?? []
  const result: Record<string, FieldResult> = {}

  for (const [name, fieldSchema] of Object.entries(schema.properties)) {
    const value = data[name] ?? ''
    result[name] = toFieldResult(
      value,
      validateField(value, fieldSchema, required.includes(name)),
    )
  }

  // Unmapped fields (file uploads, custom Respect rules) cannot be validated
  // client-side. Mark as valid and let the server handle them.
  for (const name of unmapped) {
    result[name] = { value: data[name] ?? '', is_valid: true, errors: null }
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
