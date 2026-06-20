import { describe, it, expect } from 'vitest'
import { validateObject, isAllValid, extractErrors } from '../src/validator.js'
import type { ObjectSchema } from '../src/schema.js'

// ── Helpers ──────────────────────────────────────────────────────────────────

const schema = (props: ObjectSchema['properties'], required: string[] = [], extra?: Partial<ObjectSchema>): ObjectSchema => ({
  $schema: 'https://json-schema.org/draft/2020-12/schema',
  type: 'object',
  properties: props,
  required,
  ...extra,
})

// ── validateObject: basic fields ─────────────────────────────────────────────

describe('validateObject: scalar fields', () => {
  it('validates a required string field', () => {
    const s = schema({ name: { type: 'string', minLength: 1 } }, ['name'])
    const result = validateObject({ name: 'Alice' }, s)
    expect(result.name.is_valid).toBe(true)
    expect(result.name.errors).toBeNull()
  })

  it('fails a required field that is empty', () => {
    const s = schema({ name: { type: 'string', minLength: 1 } }, ['name'])
    const result = validateObject({ name: '' }, s)
    expect(result.name.is_valid).toBe(false)
    expect(result.name.errors).not.toBeNull()
  })

  it('passes an optional empty field', () => {
    const s = schema({ note: { type: 'string' } }, [])
    const result = validateObject({ note: '' }, s)
    expect(result.note.is_valid).toBe(true)
  })

  it('passes an absent optional field', () => {
    const s = schema({ note: { type: 'string' } }, [])
    const result = validateObject({}, s)
    expect(result.note.is_valid).toBe(true)
  })

  it('validates format constraint', () => {
    const s = schema({ email: { type: 'string', format: 'email' } }, ['email'])
    const ok = validateObject({ email: 'test@example.com' }, s)
    expect(ok.email.is_valid).toBe(true)
    const bad = validateObject({ email: 'not-an-email' }, s)
    expect(bad.email.is_valid).toBe(false)
  })
})

// ── validateObject: array fields ─────────────────────────────────────────────

describe('validateObject: array fields', () => {
  it('validates array field with valid items', () => {
    const s = schema({
      tags: { type: 'array', items: { type: 'string', minLength: 2 } },
    }, ['tags'])
    const result = validateObject({ tags: ['ab', 'cd'] }, s)
    expect(result.tags.is_valid).toBe(true)
  })

  it('fails array field with short items', () => {
    const s = schema({
      tags: { type: 'array', items: { type: 'string', minLength: 3 } },
    }, ['tags'])
    const result = validateObject({ tags: ['ab'] }, s)
    expect(result.tags.is_valid).toBe(false)
  })

  it('fails required array field when empty', () => {
    const s = schema({
      tags: { type: 'array', items: { type: 'string' } },
    }, ['tags'])
    const result = validateObject({ tags: [] }, s)
    expect(result.tags.is_valid).toBe(false)
  })

  it('passes optional empty array', () => {
    const s = schema({
      tags: { type: 'array', items: { type: 'string' } },
    }, [])
    const result = validateObject({ tags: [] }, s)
    expect(result.tags.is_valid).toBe(true)
  })

  it('enforces minItems', () => {
    const s = schema({
      choices: { type: 'array', items: { type: 'string' }, minItems: 2 },
    }, ['choices'])
    const result = validateObject({ choices: ['only-one'] }, s)
    expect(result.choices.is_valid).toBe(false)
    expect(result.choices.errors).toContain('must have at least 2 items')
  })

  it('enforces maxItems', () => {
    const s = schema({
      choices: { type: 'array', items: { type: 'string' }, maxItems: 2 },
    }, ['choices'])
    const result = validateObject({ choices: ['a', 'b', 'c'] }, s)
    expect(result.choices.is_valid).toBe(false)
    expect(result.choices.errors).toContain('must have no more than 2 items')
  })

  it('detects array field via items key (no explicit type)', () => {
    const s = schema({
      tags: { items: { type: 'string' } },
    }, ['tags'])
    const result = validateObject({ tags: ['hello'] }, s)
    expect(result.tags.is_valid).toBe(true)
  })
})

// ── validateObject: conditional (if/then) ────────────────────────────────────

describe('validateObject: conditional requirements', () => {
  const condSchema = schema(
    {
      type: { type: 'string', enum: ['personal', 'company'] },
      company_name: { type: 'string' },
    },
    ['type'],
    {
      if:   { properties: { type: { const: 'company' } } },
      then: { required: ['company_name'] },
    },
  )

  it('marks conditionally required field as invalid when condition matches', () => {
    const result = validateObject({ type: 'company', company_name: '' }, condSchema)
    expect(result.company_name.is_valid).toBe(false)
    expect(result.company_name.errors).toContain('is required')
  })

  it('does not mark field invalid when condition is unmet', () => {
    const result = validateObject({ type: 'personal', company_name: '' }, condSchema)
    expect(result.company_name.is_valid).toBe(true)
  })

  it('passes when condition matches and field is provided', () => {
    const result = validateObject({ type: 'company', company_name: 'Acme' }, condSchema)
    expect(result.company_name.is_valid).toBe(true)
  })
})

describe('validateObject: allOf conditionals', () => {
  const allOfSchema = schema(
    {
      plan: { type: 'string', enum: ['free', 'enterprise'] },
      billing_email: { type: 'string', format: 'email' },
      contract: { type: 'string' },
    },
    ['plan'],
    {
      allOf: [
        { if: { properties: { plan: { const: 'enterprise' } } }, then: { required: ['billing_email'] } },
        { if: { properties: { plan: { const: 'enterprise' } } }, then: { required: ['contract'] } },
      ],
    },
  )

  it('marks both conditionally required fields invalid on match', () => {
    const result = validateObject({ plan: 'enterprise', billing_email: '', contract: '' }, allOfSchema)
    expect(result.billing_email.is_valid).toBe(false)
    expect(result.contract.is_valid).toBe(false)
  })

  it('does not trigger on free plan', () => {
    const result = validateObject({ plan: 'free', billing_email: '', contract: '' }, allOfSchema)
    expect(result.billing_email.is_valid).toBe(true)
    expect(result.contract.is_valid).toBe(true)
  })
})

// ── validateObject: x-unmapped-fields ────────────────────────────────────────

describe('validateObject: x-unmapped-fields', () => {
  it('passes unmapped fields through as valid', () => {
    const s = schema(
      { name: { type: 'string' } },
      ['name'],
      { 'x-unmapped-fields': ['phone'] },
    )
    const result = validateObject({ name: 'Alice', phone: '090-0000-0000' }, s)
    expect(result.phone.is_valid).toBe(true)
  })
})

// ── validateObject: x-transform ──────────────────────────────────────────────

describe('validateObject: x-transform trim', () => {
  const s = schema({ name: { type: 'string', 'x-transform': ['trim'] } }, ['name'])

  it('trims whitespace from value before validation', () => {
    expect(validateObject({ name: '  Alice  ' }, s).name.is_valid).toBe(true)
  })
  it('returns trimmed value in result', () => {
    expect(validateObject({ name: '  Alice  ' }, s).name.value).toBe('Alice')
  })
  it('trim producing empty string fails required check', () => {
    expect(validateObject({ name: '   ' }, s).name.is_valid).toBe(false)
  })
})

describe('validateObject: x-transform toLowerCase', () => {
  const s = schema({ code: { type: 'string', 'x-transform': ['toLowerCase'] } }, ['code'])

  it('lowercases ASCII before returning value', () => {
    expect(validateObject({ code: 'HELLO' }, s).code.value).toBe('hello')
  })
})

describe('validateObject: x-transform pipeline (trim + toLowerCase)', () => {
  const s = schema({ tag: { type: 'string', 'x-transform': ['trim', 'toLowerCase'] } }, ['tag'])

  it('applies transforms in order', () => {
    expect(validateObject({ tag: '  HELLO  ' }, s).tag.value).toBe('hello')
  })
})

// ── validateObject: x-when (equal / notEqual / field ref) ────────────────────

describe('validateObject: x-when === literal', () => {
  const s = schema(
    { type: { type: 'string' }, company_name: { type: 'string' } },
    ['type'],
    { 'x-when': [{ condition: { '===': [{ var: 'type' }, 'company'] }, require: ['company_name'] }] },
  )

  it('marks field invalid when condition matches', () => {
    const result = validateObject({ type: 'company', company_name: '' }, s)
    expect(result.company_name.is_valid).toBe(false)
  })
  it('does not trigger when condition is unmet', () => {
    const result = validateObject({ type: 'personal', company_name: '' }, s)
    expect(result.company_name.is_valid).toBe(true)
  })
  it('passes when condition matches and field is provided', () => {
    const result = validateObject({ type: 'company', company_name: 'Acme' }, s)
    expect(result.company_name.is_valid).toBe(true)
  })
})

describe('validateObject: x-when !== literal', () => {
  const s = schema(
    { role: { type: 'string' }, note: { type: 'string' } },
    ['role'],
    { 'x-when': [{ condition: { '!==': [{ var: 'role' }, 'admin'] }, require: ['note'] }] },
  )

  it('marks field invalid when role !== admin', () => {
    const result = validateObject({ role: 'user', note: '' }, s)
    expect(result.note.is_valid).toBe(false)
  })
  it('does not trigger when role === admin', () => {
    const result = validateObject({ role: 'admin', note: '' }, s)
    expect(result.note.is_valid).toBe(true)
  })
})

describe('validateObject: x-when equalsField (field ref)', () => {
  const s = schema(
    { password: { type: 'string' }, confirm: { type: 'string' }, hint: { type: 'string' } },
    ['password', 'confirm'],
    { 'x-when': [{ condition: { '===': [{ var: 'password' }, { var: 'confirm' }] }, require: ['hint'] }] },
  )

  it('triggers when two fields are equal', () => {
    const result = validateObject({ password: 'secret', confirm: 'secret', hint: '' }, s)
    expect(result.hint.is_valid).toBe(false)
  })
  it('does not trigger when fields differ', () => {
    const result = validateObject({ password: 'secret', confirm: 'other', hint: '' }, s)
    expect(result.hint.is_valid).toBe(true)
  })
})

describe('validateObject: x-when !== equalsField', () => {
  const s = schema(
    { new_pass: { type: 'string' }, old_pass: { type: 'string' }, msg: { type: 'string' } },
    ['new_pass'],
    { 'x-when': [{ condition: { '!==': [{ var: 'new_pass' }, { var: 'old_pass' }] }, require: ['msg'] }] },
  )

  it('triggers when fields differ (new !== old)', () => {
    const result = validateObject({ new_pass: 'newSecret', old_pass: 'oldSecret', msg: '' }, s)
    expect(result.msg.is_valid).toBe(false)
  })
  it('does not trigger when fields are the same', () => {
    const result = validateObject({ new_pass: 'same', old_pass: 'same', msg: '' }, s)
    expect(result.msg.is_valid).toBe(true)
  })
})

describe('validateObject: x-when takes precedence over if/then', () => {
  // When x-when is present, legacy if/then should be ignored
  const s = schema(
    { type: { type: 'string' }, a: { type: 'string' }, b: { type: 'string' } },
    ['type'],
    {
      'x-when': [{ condition: { '===': [{ var: 'type' }, 'x'] }, require: ['a'] }],
      if:   { properties: { type: { const: 'y' } } },
      then: { required: ['b'] },
    },
  )

  it('only evaluates x-when, not if/then', () => {
    const result = validateObject({ type: 'y', a: '', b: '' }, s)
    // type === 'y' would trigger if/then → b required, but x-when takes precedence
    expect(result.b?.is_valid ?? true).toBe(true)
    // type !== 'x' so x-when does not trigger a
    expect(result.a?.is_valid ?? true).toBe(true)
  })
})

// ── validateObject: x-when numeric operators ─────────────────────────────────

describe('validateObject: x-when >= (greaterThanOrEqual)', () => {
  const s = schema(
    { age: { type: 'integer' }, consent: { type: 'string' } },
    ['age'],
    { 'x-when': [{ condition: { '>=': [{ var: 'age' }, 18] }, require: ['consent'] }] },
  )
  it('triggers at boundary (18 >= 18)', () => {
    expect(validateObject({ age: '18', consent: '' }, s).consent.is_valid).toBe(false)
  })
  it('does not trigger below boundary (17 >= 18 is false)', () => {
    expect(validateObject({ age: '17', consent: '' }, s).consent.is_valid).toBe(true)
  })
})

describe('validateObject: x-when <= (lessThanOrEqual)', () => {
  const s = schema(
    { score: { type: 'integer' }, retry: { type: 'string' } },
    ['score'],
    { 'x-when': [{ condition: { '<=': [{ var: 'score' }, 50] }, require: ['retry'] }] },
  )
  it('triggers at boundary (50 <= 50)', () => {
    expect(validateObject({ score: '50', retry: '' }, s).retry.is_valid).toBe(false)
  })
  it('does not trigger above boundary (51 <= 50 is false)', () => {
    expect(validateObject({ score: '51', retry: '' }, s).retry.is_valid).toBe(true)
  })
})

describe('validateObject: x-when > (greaterThan)', () => {
  const s = schema(
    { level: { type: 'integer' }, bonus: { type: 'string' } },
    ['level'],
    { 'x-when': [{ condition: { '>': [{ var: 'level' }, 10] }, require: ['bonus'] }] },
  )
  it('triggers above boundary (11 > 10)', () => {
    expect(validateObject({ level: '11', bonus: '' }, s).bonus.is_valid).toBe(false)
  })
  it('does not trigger at boundary (10 > 10 is false)', () => {
    expect(validateObject({ level: '10', bonus: '' }, s).bonus.is_valid).toBe(true)
  })
})

describe('validateObject: x-when < (lessThan)', () => {
  const s = schema(
    { qty: { type: 'integer' }, warn: { type: 'string' } },
    ['qty'],
    { 'x-when': [{ condition: { '<': [{ var: 'qty' }, 1] }, require: ['warn'] }] },
  )
  it('triggers below boundary (0 < 1)', () => {
    expect(validateObject({ qty: '0', warn: '' }, s).warn.is_valid).toBe(false)
  })
  it('does not trigger at boundary (1 < 1 is false)', () => {
    expect(validateObject({ qty: '1', warn: '' }, s).warn.is_valid).toBe(true)
  })
})

describe('validateObject: x-when numeric equalsField', () => {
  const s = schema(
    { price: { type: 'integer' }, min_price: { type: 'integer' }, note: { type: 'string' } },
    ['price', 'min_price'],
    { 'x-when': [{ condition: { '>=': [{ var: 'price' }, { var: 'min_price' }] }, require: ['note'] }] },
  )
  it('triggers when price >= min_price', () => {
    expect(validateObject({ price: '100', min_price: '50', note: '' }, s).note.is_valid).toBe(false)
  })
  it('does not trigger when price < min_price', () => {
    expect(validateObject({ price: '30', min_price: '50', note: '' }, s).note.is_valid).toBe(true)
  })
})

// ── isAllValid / extractErrors ────────────────────────────────────────────────

describe('isAllValid', () => {
  it('returns true when all fields are valid', () => {
    const s = schema({ name: { type: 'string' } }, ['name'])
    expect(isAllValid(validateObject({ name: 'Alice' }, s))).toBe(true)
  })
  it('returns false when any field is invalid', () => {
    const s = schema({ name: { type: 'string', minLength: 5 } }, ['name'])
    expect(isAllValid(validateObject({ name: 'Bob' }, s))).toBe(false)
  })
})

describe('extractErrors', () => {
  it('returns only invalid fields', () => {
    const s = schema({ a: { type: 'string', minLength: 5 }, b: { type: 'string' } }, ['a', 'b'])
    const result = extractErrors(validateObject({ a: 'ab', b: 'ok' }, s))
    expect(Object.keys(result)).toContain('a')
    expect(Object.keys(result)).not.toContain('b')
  })
  it('returns empty object when all valid', () => {
    const s = schema({ name: { type: 'string' } }, ['name'])
    expect(extractErrors(validateObject({ name: 'Alice' }, s))).toEqual({})
  })
})
