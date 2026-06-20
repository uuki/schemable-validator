// Basic usage: validate a plain object against a schema.
//
// The schema is a JSON Schema object — typically received from a REST endpoint
// (e.g. GET /wp-json/schv/v1/contact), but here inlined for illustration.

import { validateObject, isAllValid, extractErrors } from '@uuki/schemable-validator-client'
import type { ObjectSchema } from '@uuki/schemable-validator-client'

// Example schema
const schema: ObjectSchema = {
  $schema: 'https://json-schema.org/draft/2020-12/schema',
  type: 'object',
  properties: {
    name:  { type: 'string', minLength: 1, maxLength: 100 },
    email: { type: 'string', format: 'email' },
    body:  { type: 'string', minLength: 10 },
  },
  required: ['name', 'email', 'body'],
}

// --- Valid input ---
const valid = validateObject(
  { name: 'Alice', email: 'alice@example.com', body: 'お問い合わせ内容です。' },
  schema,
)

console.log(isAllValid(valid)) // true

// --- Invalid input ---
const invalid = validateObject(
  { name: '', email: 'not-an-email', body: '' },
  schema,
)

console.log(isAllValid(invalid))      // false
console.log(extractErrors(invalid))

// {
//   name:  ['is required'],
//   email: ['must be a valid email'],
//   body:  ['is required'],
// }

// --- Per-field result shape (mirrors PHP getResult()) ---
console.log(invalid.email)
// { value: 'not-an-email', is_valid: false, errors: ['must be a valid email'] }
