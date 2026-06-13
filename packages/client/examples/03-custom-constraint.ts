// Adding a custom Constraint to the pipeline.
//
// A Constraint is a pure function: FieldState → FieldState.
// It appends to the errors array when a check fails, and passes the state
// through unchanged when the check passes. Multiple constraints compose
// with composeConstraints — all errors are accumulated, not short-circuited.

import { composeConstraints, constraintsFromSchema, validateObject } from '../src/index.js'
import type { Constraint, ObjectSchema } from '../src/index.js'

// --- Define a custom constraint ---

// Japanese phone number (hyphenated or 10/11-digit flat)
const checkJapanesePhone: Constraint = (state) =>
  /^(0\d{9,10}|0\d{1,4}-\d{1,4}-\d{3,4})$/.test(state.value)
    ? state
    : { ...state, errors: [...state.errors, '日本の電話番号形式で入力してください'] }

// Compose: built-in string type check → custom phone format check
const phoneConstraint = composeConstraints([
  constraintsFromSchema({ type: 'string' }),
  checkJapanesePhone,
])

console.log(phoneConstraint({ value: '09012345678',  errors: [] }).errors) // []
console.log(phoneConstraint({ value: '090-1234-5678', errors: [] }).errors) // []
console.log(phoneConstraint({ value: 'invalid',       errors: [] }).errors)
// ['日本の電話番号形式で入力してください']

// --- Using it with validateObject ---
//
// validateObject operates on the JSON Schema from the REST endpoint.
// Custom constraints that have no JSON Schema keyword (e.g. phone format)
// are applied after fetching the schema, by wrapping validateObject.

const schema: ObjectSchema = {
  $schema: 'https://json-schema.org/draft/2020-12/schema',
  type: 'object',
  properties: {
    name:  { type: 'string', minLength: 1 },
    phone: { type: 'string' }, // schema only ensures it's a string
  },
  required: ['name', 'phone'],
}

function validateWithCustomRules(data: Record<string, string>) {
  // 1. Run schema-derived constraints
  const base = validateObject(data, schema)

  // 2. Apply the custom phone constraint on top
  const phoneState = phoneConstraint({ value: data.phone ?? '', errors: [] })

  return {
    ...base,
    phone: {
      value: data.phone ?? '',
      is_valid: base.phone.is_valid && phoneState.errors.length === 0,
      errors:
        [...(base.phone.errors ?? []), ...phoneState.errors].length > 0
          ? [...(base.phone.errors ?? []), ...phoneState.errors]
          : null,
    },
  }
}

console.log(validateWithCustomRules({ name: 'Alice', phone: 'invalid' }).phone)
// { value: 'invalid', is_valid: false, errors: ['日本の電話番号形式で入力してください'] }
