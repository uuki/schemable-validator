// Constraint pipeline — Railway Oriented Programming for field validation.
//
// A Constraint is a pure function: FieldState → FieldState.
// It never throws; it appends to the errors array when a check fails.
// Multiple constraints compose via composeConstraints, accumulating ALL errors
// rather than short-circuiting on the first — giving users a complete picture.

import type { PropertySchema } from './schema.js'

export type FieldState = {
  readonly value: string
  readonly errors: readonly string[]
}

export type Constraint = (state: FieldState) => FieldState

// ── Composition ──────────────────────────────────────────────────────────────

const append = (state: FieldState, message: string): FieldState => ({
  ...state,
  errors: [...state.errors, message],
})

export const composeConstraints = (constraints: readonly Constraint[]): Constraint =>
  (state) => constraints.reduce((s, c) => c(s), state)

// ── Individual constraints ────────────────────────────────────────────────────

const FORMAT_RE: Readonly<Record<string, RegExp>> = {
  email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
  uri: /^https?:\/\/[^\s]+$/,
}

export const checkType = (type: PropertySchema['type']): Constraint => (state) => {
  if (!type) return state
  const types = Array.isArray(type) ? type : [type]
  const primary = types.find((t) => t !== 'null')
  if (!primary) return state

  if (primary === 'integer') {
    const n = Number(state.value)
    if (!Number.isFinite(n) || !Number.isInteger(n)) {
      return append(state, 'must be an integer')
    }
  } else if (primary === 'number') {
    if (!Number.isFinite(Number(state.value))) {
      return append(state, 'must be a number')
    }
  } else if (primary === 'boolean') {
    const accepted = new Set(['true', 'false', '1', '0', 'on', 'off', 'yes', 'no'])
    if (!accepted.has(state.value.toLowerCase())) {
      return append(state, 'must be a boolean')
    }
  }
  return state
}

export const checkMinLength = (min: number): Constraint => (state) =>
  state.value.length >= min
    ? state
    : append(state, `must be at least ${min} character${min !== 1 ? 's' : ''} long`)

export const checkMaxLength = (max: number): Constraint => (state) =>
  state.value.length <= max
    ? state
    : append(state, `must be no more than ${max} character${max !== 1 ? 's' : ''} long`)

export const checkMinimum = (min: number): Constraint => (state) => {
  const n = Number(state.value)
  return Number.isFinite(n) && n >= min ? state : append(state, `must be at least ${min}`)
}

export const checkMaximum = (max: number): Constraint => (state) => {
  const n = Number(state.value)
  return Number.isFinite(n) && n <= max ? state : append(state, `must be no more than ${max}`)
}

export const checkFormat = (format: string): Constraint => (state) => {
  const re = FORMAT_RE[format]
  if (!re) return state
  return re.test(state.value) ? state : append(state, `must be a valid ${format}`)
}

export const checkPattern = (pattern: string): Constraint => (state) => {
  try {
    return new RegExp(pattern, 'u').test(state.value)
      ? state
      : append(state, 'must match the required format')
  } catch {
    return state // invalid regex in schema — skip silently
  }
}

export const checkEnum = (values: readonly string[]): Constraint => (state) =>
  values.includes(state.value)
    ? state
    : append(state, `must be one of: ${values.join(', ')}`)

// ── Schema → composed Constraint ─────────────────────────────────────────────

export const constraintsFromSchema = (schema: PropertySchema): Constraint => {
  const cs: Constraint[] = []

  if (schema.type !== undefined) cs.push(checkType(schema.type))
  if (schema.minLength !== undefined) cs.push(checkMinLength(schema.minLength))
  if (schema.maxLength !== undefined) cs.push(checkMaxLength(schema.maxLength))
  if (schema.minimum !== undefined) cs.push(checkMinimum(schema.minimum))
  if (schema.maximum !== undefined) cs.push(checkMaximum(schema.maximum))
  if (schema.format !== undefined) cs.push(checkFormat(schema.format))
  if (schema.pattern !== undefined) cs.push(checkPattern(schema.pattern))
  if (schema.enum !== undefined) cs.push(checkEnum(schema.enum))

  return composeConstraints(cs)
}
