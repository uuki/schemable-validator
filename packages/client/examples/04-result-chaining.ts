// ROP: wrapping validation in Result and chaining with flatMap.
//
// validateObject returns ValidationResult directly (no Result wrapper).
// This example shows how to lift it into Result when you want to
// thread success/failure through a pipeline without nested if-checks.

import { validateObject, isAllValid, extractErrors, ok, err, flatMap } from '@uuki/schemable-validator-client'
import type { Result, ObjectSchema, ValidationResult } from '@uuki/schemable-validator-client'

type ValidationErrors = Readonly<Record<string, readonly string[]>>

// Lift validateObject into Result: Ok if all valid, Err with field errors otherwise
const validate = (
  data: Record<string, string>,
  schema: ObjectSchema,
): Result<ValidationResult, ValidationErrors> => {
  const result = validateObject(data, schema)
  return isAllValid(result) ? ok(result) : err(extractErrors(result))
}

// --- Pipeline ---

const schema: ObjectSchema = {
  $schema: 'https://json-schema.org/draft/2020-12/schema',
  type: 'object',
  properties: {
    name:  { type: 'string', minLength: 1 },
    email: { type: 'string', format: 'email' },
  },
  required: ['name', 'email'],
}

type Payload = { name: string; email: string; sanitized: true }

const result = flatMap(
  validate({ name: 'Alice', email: 'alice@example.com' }, schema),
  (fields): Result<Payload, ValidationErrors> =>
    ok({
      name:      fields.name.value,
      email:     fields.email.value,
      sanitized: true,
    }),
)

if (result._tag === 'Ok') {
  console.log('submit', result.value)
  // submit { name: 'Alice', email: 'alice@example.com', sanitized: true }
} else {
  console.error('errors', result.error)
}

// --- Failure path ---

const failed = validate({ name: '', email: 'bad' }, schema)
// Err({ name: ['is required'], email: ['must be a valid email'] })

const withFallback = flatMap(
  failed,
  () => err({ _form: ['unexpected: should not reach here'] } as ValidationErrors),
)
console.log(withFallback._tag)   // 'Err'
console.log(withFallback._tag === 'Err' && withFallback.error)
// { name: ['is required'], email: ['must be a valid email'] }
