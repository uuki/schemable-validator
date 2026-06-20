import { describe, it, expect, vi, afterEach } from 'vitest'
import { z } from 'zod'
import * as v from 'valibot'
import { toZodSchema, checkZodSchema, sv as zodSv, createSv as zodCreateSv }         from '../src/adapters/zod.js'
import type { ZodRefiner, ZodAsyncRefiner }                                           from '../src/adapters/zod.js'
import { toValibotSchema, checkValibotSchema, sv as valibotSv, createSv as valibotCreateSv } from '../src/adapters/valibot.js'
import type { ValibotRefiner, ValibotAsyncRefiner }                                         from '../src/adapters/valibot.js'
import type { ObjectSchema } from '../src/schema.js'

// ── helpers ───────────────────────────────────────────────────────────────────

const schema = (
  props: ObjectSchema['properties'],
  required: string[] = [],
  extra?: Partial<ObjectSchema>,
): ObjectSchema => ({
  $schema: 'https://json-schema.org/draft/2020-12/schema',
  type: 'object',
  properties: props,
  required,
  ...extra,
})

afterEach(() => vi.restoreAllMocks())

// ── toZodSchema ───────────────────────────────────────────────────────────────

describe('toZodSchema: core types', () => {
  it('required string', () => {
    const s = toZodSchema(schema({ name: { type: 'string' } }, ['name']))
    expect(s.safeParse({ name: 'Alice' }).success).toBe(true)
    expect(s.safeParse({ name: 123 }).success).toBe(false)
    expect(s.safeParse({}).success).toBe(false)
  })

  it('optional string', () => {
    const s = toZodSchema(schema({ note: { type: 'string' } }))
    expect(s.safeParse({}).success).toBe(true)
  })

  it('minLength / maxLength', () => {
    const s = toZodSchema(
      schema({ name: { type: 'string', minLength: 2, maxLength: 5 } }, ['name']),
    )
    expect(s.safeParse({ name: 'Al' }).success).toBe(true)
    expect(s.safeParse({ name: 'A' }).success).toBe(false)
    expect(s.safeParse({ name: 'AliceX' }).success).toBe(false)
  })

  it('integer min / max', () => {
    const s = toZodSchema(schema({ age: { type: 'integer', minimum: 18, maximum: 99 } }, ['age']))
    expect(s.safeParse({ age: 18 }).success).toBe(true)
    expect(s.safeParse({ age: 17 }).success).toBe(false)
    expect(s.safeParse({ age: 100 }).success).toBe(false)
    expect(s.safeParse({ age: 1.5 }).success).toBe(false)
  })

  it('number min / max', () => {
    const s = toZodSchema(schema({ score: { type: 'number', minimum: 0, maximum: 1 } }, ['score']))
    expect(s.safeParse({ score: 0.5 }).success).toBe(true)
    expect(s.safeParse({ score: -0.1 }).success).toBe(false)
  })

  it('boolean', () => {
    const s = toZodSchema(schema({ active: { type: 'boolean' } }, ['active']))
    expect(s.safeParse({ active: true }).success).toBe(true)
    expect(s.safeParse({ active: 'true' }).success).toBe(false)
  })

  it('enum', () => {
    const s = toZodSchema(schema({ role: { type: 'string', enum: ['admin', 'user'] } }, ['role']))
    expect(s.safeParse({ role: 'admin' }).success).toBe(true)
    expect(s.safeParse({ role: 'guest' }).success).toBe(false)
  })

  it('skips x-unmapped-fields', () => {
    const s = toZodSchema(
      schema({ name: { type: 'string' }, avatar: { type: 'string' } }, ['name'], {
        'x-unmapped-fields': ['avatar'],
      }),
    )
    expect(s.shape).toHaveProperty('name')
    expect(s.shape).not.toHaveProperty('avatar')
  })
})

describe('toZodSchema: string formats', () => {
  it('email', () => {
    const s = toZodSchema(schema({ e: { type: 'string', format: 'email' } }, ['e']))
    expect(s.safeParse({ e: 'a@b.com' }).success).toBe(true)
    expect(s.safeParse({ e: 'bad' }).success).toBe(false)
  })

  it('uri', () => {
    const s = toZodSchema(schema({ u: { type: 'string', format: 'uri' } }, ['u']))
    expect(s.safeParse({ u: 'https://example.com' }).success).toBe(true)
    expect(s.safeParse({ u: 'bad' }).success).toBe(false)
  })

  it('uuid', () => {
    const s = toZodSchema(schema({ id: { type: 'string', format: 'uuid' } }, ['id']))
    expect(s.safeParse({ id: '550e8400-e29b-41d4-a716-446655440000' }).success).toBe(true)
    expect(s.safeParse({ id: 'bad' }).success).toBe(false)
  })

  it('ipv4', () => {
    const s = toZodSchema(schema({ ip: { type: 'string', format: 'ipv4' } }, ['ip']))
    expect(s.safeParse({ ip: '192.168.0.1' }).success).toBe(true)
    expect(s.safeParse({ ip: '::1' }).success).toBe(false)
  })

  it('ipv6', () => {
    const s = toZodSchema(schema({ ip: { type: 'string', format: 'ipv6' } }, ['ip']))
    expect(s.safeParse({ ip: '::1' }).success).toBe(true)
    expect(s.safeParse({ ip: '192.168.0.1' }).success).toBe(false)
  })

  it('date', () => {
    const s = toZodSchema(schema({ d: { type: 'string', format: 'date' } }, ['d']))
    expect(s.safeParse({ d: '2024-01-15' }).success).toBe(true)
    expect(s.safeParse({ d: '01-15-2024' }).success).toBe(false)
  })

  it('date-time', () => {
    const s = toZodSchema(schema({ dt: { type: 'string', format: 'date-time' } }, ['dt']))
    expect(s.safeParse({ dt: '2024-01-15T10:30:00Z' }).success).toBe(true)
    expect(s.safeParse({ dt: '2024-01-15' }).success).toBe(false)
  })

  it('time', () => {
    const s = toZodSchema(schema({ t: { type: 'string', format: 'time' } }, ['t']))
    expect(s.safeParse({ t: '10:30:00' }).success).toBe(true)
    expect(s.safeParse({ t: 'bad' }).success).toBe(false)
  })

  it('pattern', () => {
    const s = toZodSchema(schema({ c: { type: 'string', pattern: '^[A-Z]{3}$' } }, ['c']))
    expect(s.safeParse({ c: 'ABC' }).success).toBe(true)
    expect(s.safeParse({ c: 'abc' }).success).toBe(false)
  })
})

describe('toZodSchema: array type', () => {
  it('array of strings', () => {
    const s = toZodSchema(
      schema({ tags: { type: 'array', items: { type: 'string' } } }, ['tags']),
    )
    expect(s.safeParse({ tags: ['a', 'b'] }).success).toBe(true)
    expect(s.safeParse({ tags: 'not-array' }).success).toBe(false)
    expect(s.safeParse({ tags: [1, 2] }).success).toBe(false)
  })

  it('minItems / maxItems', () => {
    const s = toZodSchema(
      schema({ tags: { type: 'array', items: { type: 'string' }, minItems: 1, maxItems: 3 } }, ['tags']),
    )
    expect(s.safeParse({ tags: ['a'] }).success).toBe(true)
    expect(s.safeParse({ tags: [] }).success).toBe(false)
    expect(s.safeParse({ tags: ['a', 'b', 'c', 'd'] }).success).toBe(false)
  })
})

describe('toZodSchema: nullable', () => {
  it('nullable string accepts null', () => {
    const s = toZodSchema(
      schema({ bio: { type: ['string', 'null'] } }, ['bio']),
    )
    expect(s.safeParse({ bio: 'hello' }).success).toBe(true)
    expect(s.safeParse({ bio: null }).success).toBe(true)
    expect(s.safeParse({ bio: 123 }).success).toBe(false)
  })

  it('nullable integer accepts null', () => {
    const s = toZodSchema(
      schema({ count: { type: ['integer', 'null'] } }, ['count']),
    )
    expect(s.safeParse({ count: 5 }).success).toBe(true)
    expect(s.safeParse({ count: null }).success).toBe(true)
    expect(s.safeParse({ count: 'x' }).success).toBe(false)
  })
})

describe('toZodSchema: onUnknown', () => {
  it('hostname with onUnknown: "throw" throws', () => {
    expect(() =>
      toZodSchema(
        schema({ host: { type: 'string', format: 'hostname' } }, ['host']),
        { onUnknown: 'throw' },
      ),
    ).toThrow('hostname')
  })

  it('hostname with onUnknown: "warn" warns and falls back to z.unknown()', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    const s = toZodSchema(
      schema({ host: { type: 'string', format: 'hostname' } }, ['host']),
      { onUnknown: 'warn' },
    )
    expect(warn).toHaveBeenCalledWith(expect.stringContaining('hostname'))
    // z.unknown() accepts anything
    expect(s.safeParse({ host: 'example.com' }).success).toBe(true)
    expect(s.safeParse({ host: 123 }).success).toBe(true)
  })

  it('hostname with onUnknown: callback uses returned schema', () => {
    const s = toZodSchema(
      schema({ host: { type: 'string', format: 'hostname' } }, ['host']),
      { onUnknown: (_key, _field) => z.string().regex(/^[a-z0-9.-]+$/) },
    )
    expect(s.safeParse({ host: 'example.com' }).success).toBe(true)
    expect(s.safeParse({ host: '!!' }).success).toBe(false)
  })
})

// ── checkZodSchema ────────────────────────────────────────────────────────────

describe('checkZodSchema', () => {
  it('returns empty unsupported for fully supported schema', () => {
    const report = checkZodSchema(
      schema({ name: { type: 'string' }, age: { type: 'integer' } }, ['name']),
    )
    expect(report.unsupported).toHaveLength(0)
    expect(report.supported).toContain('name')
    expect(report.supported).toContain('age')
  })

  it('reports unsupported fields without throwing', () => {
    const report = checkZodSchema(
      schema({
        name: { type: 'string' },
        host: { type: 'string', format: 'hostname' },
      }),
    )
    expect(report.supported).toContain('name')
    expect(report.unsupported).toHaveLength(1)
    expect(report.unsupported[0].key).toBe('host')
    expect(report.unsupported[0].reason).toMatch('hostname')
  })

  it('skips x-unmapped-fields in report', () => {
    const report = checkZodSchema(
      schema({ name: { type: 'string' }, avatar: { type: 'string' } }, [], {
        'x-unmapped-fields': ['avatar'],
      }),
    )
    expect(report.supported).not.toContain('avatar')
    expect(report.unsupported.map(u => u.key)).not.toContain('avatar')
  })
})

// ── toValibotSchema ───────────────────────────────────────────────────────────

describe('toValibotSchema: core types', () => {
  it('required string', () => {
    const s = toValibotSchema(schema({ name: { type: 'string' } }, ['name']))
    expect(v.safeParse(s, { name: 'Alice' }).success).toBe(true)
    expect(v.safeParse(s, { name: 123 }).success).toBe(false)
    expect(v.safeParse(s, {}).success).toBe(false)
  })

  it('optional string', () => {
    const s = toValibotSchema(schema({ note: { type: 'string' } }))
    expect(v.safeParse(s, {}).success).toBe(true)
  })

  it('minLength / maxLength', () => {
    const s = toValibotSchema(
      schema({ name: { type: 'string', minLength: 2, maxLength: 5 } }, ['name']),
    )
    expect(v.safeParse(s, { name: 'Al' }).success).toBe(true)
    expect(v.safeParse(s, { name: 'A' }).success).toBe(false)
    expect(v.safeParse(s, { name: 'AliceX' }).success).toBe(false)
  })

  it('integer min / max', () => {
    const s = toValibotSchema(schema({ age: { type: 'integer', minimum: 18, maximum: 99 } }, ['age']))
    expect(v.safeParse(s, { age: 18 }).success).toBe(true)
    expect(v.safeParse(s, { age: 17 }).success).toBe(false)
    expect(v.safeParse(s, { age: 100 }).success).toBe(false)
    expect(v.safeParse(s, { age: 1.5 }).success).toBe(false)
  })

  it('number min / max', () => {
    const s = toValibotSchema(schema({ score: { type: 'number', minimum: 0, maximum: 1 } }, ['score']))
    expect(v.safeParse(s, { score: 0.5 }).success).toBe(true)
    expect(v.safeParse(s, { score: -0.1 }).success).toBe(false)
  })

  it('boolean', () => {
    const s = toValibotSchema(schema({ active: { type: 'boolean' } }, ['active']))
    expect(v.safeParse(s, { active: true }).success).toBe(true)
    expect(v.safeParse(s, { active: 'true' }).success).toBe(false)
  })

  it('enum', () => {
    const s = toValibotSchema(schema({ role: { type: 'string', enum: ['admin', 'user'] } }, ['role']))
    expect(v.safeParse(s, { role: 'admin' }).success).toBe(true)
    expect(v.safeParse(s, { role: 'guest' }).success).toBe(false)
  })

  it('skips x-unmapped-fields', () => {
    const s = toValibotSchema(
      schema({ name: { type: 'string' }, avatar: { type: 'string' } }, ['name'], {
        'x-unmapped-fields': ['avatar'],
      }),
    )
    expect(Object.keys(s.entries)).toContain('name')
    expect(Object.keys(s.entries)).not.toContain('avatar')
  })
})

describe('toValibotSchema: string formats', () => {
  it('email', () => {
    const s = toValibotSchema(schema({ e: { type: 'string', format: 'email' } }, ['e']))
    expect(v.safeParse(s, { e: 'a@b.com' }).success).toBe(true)
    expect(v.safeParse(s, { e: 'bad' }).success).toBe(false)
  })

  it('uri', () => {
    const s = toValibotSchema(schema({ u: { type: 'string', format: 'uri' } }, ['u']))
    expect(v.safeParse(s, { u: 'https://example.com' }).success).toBe(true)
    expect(v.safeParse(s, { u: 'bad' }).success).toBe(false)
  })

  it('uuid', () => {
    const s = toValibotSchema(schema({ id: { type: 'string', format: 'uuid' } }, ['id']))
    expect(v.safeParse(s, { id: '550e8400-e29b-41d4-a716-446655440000' }).success).toBe(true)
    expect(v.safeParse(s, { id: 'bad' }).success).toBe(false)
  })

  it('ipv4', () => {
    const s = toValibotSchema(schema({ ip: { type: 'string', format: 'ipv4' } }, ['ip']))
    expect(v.safeParse(s, { ip: '192.168.0.1' }).success).toBe(true)
    expect(v.safeParse(s, { ip: '::1' }).success).toBe(false)
  })

  it('ipv6', () => {
    const s = toValibotSchema(schema({ ip: { type: 'string', format: 'ipv6' } }, ['ip']))
    expect(v.safeParse(s, { ip: '::1' }).success).toBe(true)
    expect(v.safeParse(s, { ip: '192.168.0.1' }).success).toBe(false)
  })

  it('date', () => {
    const s = toValibotSchema(schema({ d: { type: 'string', format: 'date' } }, ['d']))
    expect(v.safeParse(s, { d: '2024-01-15' }).success).toBe(true)
    expect(v.safeParse(s, { d: '01-15-2024' }).success).toBe(false)
  })

  it('date-time', () => {
    const s = toValibotSchema(schema({ dt: { type: 'string', format: 'date-time' } }, ['dt']))
    expect(v.safeParse(s, { dt: '2024-01-15T10:30:00Z' }).success).toBe(true)
    expect(v.safeParse(s, { dt: '2024-01-15' }).success).toBe(false)
  })

  it('time', () => {
    const s = toValibotSchema(schema({ t: { type: 'string', format: 'time' } }, ['t']))
    expect(v.safeParse(s, { t: '10:30:00' }).success).toBe(true)
    expect(v.safeParse(s, { t: 'bad' }).success).toBe(false)
  })

  it('pattern', () => {
    const s = toValibotSchema(schema({ c: { type: 'string', pattern: '^[A-Z]{3}$' } }, ['c']))
    expect(v.safeParse(s, { c: 'ABC' }).success).toBe(true)
    expect(v.safeParse(s, { c: 'abc' }).success).toBe(false)
  })
})

describe('toValibotSchema: array type', () => {
  it('array of strings', () => {
    const s = toValibotSchema(
      schema({ tags: { type: 'array', items: { type: 'string' } } }, ['tags']),
    )
    expect(v.safeParse(s, { tags: ['a', 'b'] }).success).toBe(true)
    expect(v.safeParse(s, { tags: 'not-array' }).success).toBe(false)
    expect(v.safeParse(s, { tags: [1, 2] }).success).toBe(false)
  })

  it('minItems / maxItems', () => {
    const s = toValibotSchema(
      schema({ tags: { type: 'array', items: { type: 'string' }, minItems: 1, maxItems: 3 } }, ['tags']),
    )
    expect(v.safeParse(s, { tags: ['a'] }).success).toBe(true)
    expect(v.safeParse(s, { tags: [] }).success).toBe(false)
    expect(v.safeParse(s, { tags: ['a', 'b', 'c', 'd'] }).success).toBe(false)
  })
})

describe('toValibotSchema: nullable', () => {
  it('nullable string accepts null', () => {
    const s = toValibotSchema(
      schema({ bio: { type: ['string', 'null'] } }, ['bio']),
    )
    expect(v.safeParse(s, { bio: 'hello' }).success).toBe(true)
    expect(v.safeParse(s, { bio: null }).success).toBe(true)
    expect(v.safeParse(s, { bio: 123 }).success).toBe(false)
  })

  it('nullable integer accepts null', () => {
    const s = toValibotSchema(
      schema({ count: { type: ['integer', 'null'] } }, ['count']),
    )
    expect(v.safeParse(s, { count: 5 }).success).toBe(true)
    expect(v.safeParse(s, { count: null }).success).toBe(true)
    expect(v.safeParse(s, { count: 'x' }).success).toBe(false)
  })
})

describe('toValibotSchema: onUnknown', () => {
  it('hostname with onUnknown: "throw" throws', () => {
    expect(() =>
      toValibotSchema(
        schema({ host: { type: 'string', format: 'hostname' } }, ['host']),
        { onUnknown: 'throw' },
      ),
    ).toThrow('hostname')
  })

  it('hostname with onUnknown: "warn" warns and falls back to v.unknown()', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    const s = toValibotSchema(
      schema({ host: { type: 'string', format: 'hostname' } }, ['host']),
      { onUnknown: 'warn' },
    )
    expect(warn).toHaveBeenCalledWith(expect.stringContaining('hostname'))
    expect(v.safeParse(s, { host: 'example.com' }).success).toBe(true)
    expect(v.safeParse(s, { host: 123 }).success).toBe(true)
  })

  it('hostname with onUnknown: callback uses returned schema', () => {
    const s = toValibotSchema(
      schema({ host: { type: 'string', format: 'hostname' } }, ['host']),
      { onUnknown: (_key, _field) => v.pipe(v.string(), v.regex(/^[a-z0-9.-]+$/)) },
    )
    expect(v.safeParse(s, { host: 'example.com' }).success).toBe(true)
    expect(v.safeParse(s, { host: '!!' }).success).toBe(false)
  })
})

// ── checkValibotSchema ────────────────────────────────────────────────────────

describe('checkValibotSchema', () => {
  it('returns empty unsupported for fully supported schema', () => {
    const report = checkValibotSchema(
      schema({ name: { type: 'string' }, age: { type: 'integer' } }, ['name']),
    )
    expect(report.unsupported).toHaveLength(0)
    expect(report.supported).toContain('name')
    expect(report.supported).toContain('age')
  })

  it('reports unsupported fields without throwing', () => {
    const report = checkValibotSchema(
      schema({
        name: { type: 'string' },
        host: { type: 'string', format: 'hostname' },
      }),
    )
    expect(report.supported).toContain('name')
    expect(report.unsupported).toHaveLength(1)
    expect(report.unsupported[0].key).toBe('host')
    expect(report.unsupported[0].reason).toMatch('hostname')
  })

  it('skips x-unmapped-fields in report', () => {
    const report = checkValibotSchema(
      schema({ name: { type: 'string' }, avatar: { type: 'string' } }, [], {
        'x-unmapped-fields': ['avatar'],
      }),
    )
    expect(report.supported).not.toContain('avatar')
    expect(report.unsupported.map(u => u.key)).not.toContain('avatar')
  })
})

// ── Zod sv() builder ──────────────────────────────────────────────────────────

describe('zodSv: basic build', () => {
  it('build() with no methods returns same result as toZodSchema', () => {
    const json  = schema({ name: { type: 'string' }, age: { type: 'integer' } }, ['name'])
    const built = zodSv(json).build()
    const direct = toZodSchema(json)
    expect(built.safeParse({ name: 'Alice', age: 25 }).success).toBe(true)
    expect(built.safeParse({ age: 25 }).success).toBe(direct.safeParse({ age: 25 }).success)
  })

  it('build() respects required / optional', () => {
    const s = zodSv(schema({ a: { type: 'string' }, b: { type: 'string' } }, ['a'])).build()
    expect(s.safeParse({ a: 'x' }).success).toBe(true)
    expect(s.safeParse({}).success).toBe(false)
  })
})

describe('zodSv: .extend()', () => {
  it('adds extra fields not in JSON Schema', () => {
    const json = schema({ name: { type: 'string' } }, ['name'])
    const s    = zodSv(json).extend({ file: z.instanceof(File).optional() }).build()
    expect(s.safeParse({ name: 'Alice' }).success).toBe(true)
    expect(s.safeParse({ name: 'Alice', file: new File([], 'a.jpg') }).success).toBe(true)
  })

  it('overrides an existing field', () => {
    const json = schema({ status: { type: 'string' } }, ['status'])
    const s    = zodSv(json).extend({ status: z.enum(['active', 'inactive']) }).build()
    expect(s.safeParse({ status: 'active' }).success).toBe(true)
    expect(s.safeParse({ status: 'unknown' }).success).toBe(false)
  })
})

describe('zodSv: .when()', () => {
  const whenJson: ObjectSchema = {
    ...schema(
      { type: { type: 'string', enum: ['company', 'individual'] }, company_name: { type: 'string' } },
      ['type'],
    ),
    'x-when': [{ condition: { '===': [{ var: 'type' }, 'company'] }, require: ['company_name'] }],
  }

  it('enforces conditional requirement when condition matches', () => {
    const s = zodSv(whenJson).when().build()
    expect(s.safeParse({ type: 'company', company_name: 'ACME' }).success).toBe(true)
    expect(s.safeParse({ type: 'company' }).success).toBe(false)
  })

  it('does not enforce requirement when condition does not match', () => {
    const s = zodSv(whenJson).when().build()
    expect(s.safeParse({ type: 'individual' }).success).toBe(true)
  })

  it('is a no-op when schema has no x-when', () => {
    const s = zodSv(schema({ name: { type: 'string' } }, ['name'])).when().build()
    expect(s.safeParse({ name: 'Alice' }).success).toBe(true)
  })
})

describe('zodSv: .refine()', () => {
  it('runs synchronous cross-field validator', () => {
    const checkRange: ZodRefiner = (data, ctx) => {
      if ((data.min as number) >= (data.max as number)) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['max'], message: 'max must exceed min' })
      }
    }
    const json = schema({ min: { type: 'integer' }, max: { type: 'integer' } }, ['min', 'max'])
    const s    = zodSv(json).refine(checkRange).build()
    expect(s.safeParse({ min: 1, max: 10 }).success).toBe(true)
    expect(s.safeParse({ min: 10, max: 5 }).success).toBe(false)
  })

  it('multiple refine() calls all execute', () => {
    const addError: ZodRefiner = (_data, ctx) =>
      ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['x'], message: 'err' })
    const json = schema({ x: { type: 'string' } }, ['x'])
    const s    = zodSv(json).refine(addError).refine(addError).build()
    const res  = s.safeParse({ x: 'ok' })
    expect(res.success).toBe(false)
    if (!res.success) expect(res.error.issues.length).toBeGreaterThanOrEqual(2)
  })
})

describe('zodSv: .refineAsync()', () => {
  it('runs async validator (requires parseAsync)', async () => {
    const checkAsync: ZodAsyncRefiner = async (data, ctx) => {
      await Promise.resolve()
      if (data.name === 'taken') {
        ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['name'], message: 'Already taken' })
      }
    }
    const json = schema({ name: { type: 'string' } }, ['name'])
    const s    = zodSv(json).refineAsync(checkAsync).build()
    await expect(s.parseAsync({ name: 'alice' })).resolves.toBeTruthy()
    await expect(s.parseAsync({ name: 'taken' })).rejects.toThrow()
  })

  it('mixed sync + async refiners all run', async () => {
    const syncErr: ZodRefiner = (_d, ctx) =>
      ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['a'], message: 'sync' })
    const asyncErr: ZodAsyncRefiner = async (_d, ctx) => {
      ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['b'], message: 'async' })
    }
    const json = schema({ a: { type: 'string' }, b: { type: 'string' } }, ['a', 'b'])
    const s    = zodSv(json).refine(syncErr).refineAsync(asyncErr).build()
    const res  = await s.safeParseAsync({ a: 'x', b: 'y' })
    expect(res.success).toBe(false)
    if (!res.success) {
      const paths = res.error.issues.map(i => i.path[0])
      expect(paths).toContain('a')
      expect(paths).toContain('b')
    }
  })
})

describe('zodSv: .when() call order independence', () => {
  const whenJson: ObjectSchema = {
    ...schema(
      { type: { type: 'string', enum: ['corp'] }, corp_id: { type: 'string' } },
      ['type'],
    ),
    'x-when': [{ condition: { '===': [{ var: 'type' }, 'corp'] }, require: ['corp_id'] }],
  }

  it('refine before when produces same result as when before refine', () => {
    const errRefiner: ZodRefiner = (_d, ctx) =>
      ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['extra'], message: 'test' })

    const s1 = zodSv(whenJson).refine(errRefiner).when().build()
    const s2 = zodSv(whenJson).when().refine(errRefiner).build()

    const r1 = s1.safeParse({ type: 'corp' })
    const r2 = s2.safeParse({ type: 'corp' })

    expect(r1.success).toBe(false)
    expect(r2.success).toBe(false)
    if (!r1.success && !r2.success) {
      const paths1 = r1.error.issues.map(i => i.path[0] as string)
      const paths2 = r2.error.issues.map(i => i.path[0] as string)
      expect(paths1).toContain('corp_id')
      expect(paths1).toContain('extra')
      expect(paths2).toContain('corp_id')
      expect(paths2).toContain('extra')
    }
  })
})

describe('zodCreateSv: factory config', () => {
  it('shares onUnknown policy across schemas', () => {
    const factory = zodCreateSv({ onUnknown: 'throw' })
    const json    = schema({ host: { type: 'string', format: 'hostname' } }, ['host'])
    expect(() => factory(json).build()).toThrow('hostname')
  })

  it('per-schema onUnknown() overrides factory config', () => {
    const factory = zodCreateSv({ onUnknown: 'throw' })
    const json    = schema({ host: { type: 'string', format: 'hostname' } }, ['host'])
    expect(() => factory(json).onUnknown('warn').build()).not.toThrow()
  })

  it('check: true warns during build()', () => {
    const warn    = vi.spyOn(console, 'warn').mockImplementation(() => {})
    const factory = zodCreateSv({ check: true })
    const json    = schema({ host: { type: 'string', format: 'hostname' } }, ['host'])
    factory(json).build()
    expect(warn).toHaveBeenCalledWith(expect.stringContaining('[schemable]'), expect.anything())
  })
})

// ── Valibot sv() builder ──────────────────────────────────────────────────────

describe('valibotSv: basic build', () => {
  it('build() with no methods returns same result as toValibotSchema', () => {
    const json   = schema({ name: { type: 'string' }, age: { type: 'integer' } }, ['name'])
    const built  = valibotSv(json).build()
    expect(v.safeParse(built, { name: 'Alice', age: 25 }).success).toBe(true)
    expect(v.safeParse(built, { age: 25 }).success).toBe(false)
  })

  it('build() respects required / optional', () => {
    const s = valibotSv(schema({ a: { type: 'string' }, b: { type: 'string' } }, ['a'])).build()
    expect(v.safeParse(s, { a: 'x' }).success).toBe(true)
    expect(v.safeParse(s, {}).success).toBe(false)
  })
})

describe('valibotSv: .extend()', () => {
  it('adds extra fields not in JSON Schema', () => {
    const json = schema({ name: { type: 'string' } }, ['name'])
    const s    = valibotSv(json).extend({ score: v.optional(v.number()) }).build()
    expect(v.safeParse(s, { name: 'Alice' }).success).toBe(true)
    expect(v.safeParse(s, { name: 'Alice', score: 9.5 }).success).toBe(true)
    expect(v.safeParse(s, { name: 'Alice', score: 'bad' }).success).toBe(false)
  })

  it('overrides an existing field', () => {
    const json = schema({ status: { type: 'string' } }, ['status'])
    const s    = valibotSv(json).extend({ status: v.picklist(['active', 'inactive']) }).build()
    expect(v.safeParse(s, { status: 'active' }).success).toBe(true)
    expect(v.safeParse(s, { status: 'unknown' }).success).toBe(false)
  })
})

describe('valibotSv: .when()', () => {
  const whenJson: ObjectSchema = {
    ...schema(
      { type: { type: 'string', enum: ['company', 'individual'] }, company_name: { type: 'string' } },
      ['type'],
    ),
    'x-when': [{ condition: { '===': [{ var: 'type' }, 'company'] }, require: ['company_name'] }],
  }

  it('enforces conditional requirement when condition matches', () => {
    const s = valibotSv(whenJson).when().build()
    expect(v.safeParse(s, { type: 'company', company_name: 'ACME' }).success).toBe(true)
    expect(v.safeParse(s, { type: 'company' }).success).toBe(false)
  })

  it('does not enforce requirement when condition does not match', () => {
    const s = valibotSv(whenJson).when().build()
    expect(v.safeParse(s, { type: 'individual' }).success).toBe(true)
  })

  it('is a no-op when schema has no x-when', () => {
    const s = valibotSv(schema({ name: { type: 'string' } }, ['name'])).when().build()
    expect(v.safeParse(s, { name: 'Alice' }).success).toBe(true)
  })
})

describe('valibotSv: .refine()', () => {
  it('runs synchronous cross-field validator', () => {
    const checkRange: ValibotRefiner = ({ dataset, addIssue }) => {
      if (!dataset.typed) return
      const d = dataset.value as { min: number; max: number }
      if (d.min >= d.max) addIssue({ message: 'max must exceed min' })
    }
    const json = schema({ min: { type: 'integer' }, max: { type: 'integer' } }, ['min', 'max'])
    const s    = valibotSv(json).refine(checkRange).build()
    expect(v.safeParse(s, { min: 1, max: 10 }).success).toBe(true)
    expect(v.safeParse(s, { min: 10, max: 5 }).success).toBe(false)
  })
})

describe('valibotSv: .refineAsync()', () => {
  it('runs async validator (requires safeParseAsync)', async () => {
    const checkAsync: ValibotAsyncRefiner = async ({ dataset, addIssue }) => {
      if (!dataset.typed) return
      await Promise.resolve()
      const d = dataset.value as { name: string }
      if (d.name === 'taken') addIssue({ message: 'Already taken' })
    }
    const json = schema({ name: { type: 'string' } }, ['name'])
    const s    = valibotSv(json).refineAsync(checkAsync).build()
    await expect(v.safeParseAsync(s, { name: 'alice' })).resolves.toMatchObject({ success: true })
    const fail = await v.safeParseAsync(s, { name: 'taken' })
    expect(fail.success).toBe(false)
  })

  it('auto-selects objectAsync when async refiner is used', () => {
    const asyncFn: ValibotAsyncRefiner = async () => { await Promise.resolve() }
    const json = schema({ name: { type: 'string' } }, ['name'])
    const s    = valibotSv(json).refineAsync(asyncFn).build()
    // pipeAsync/objectAsync have async: true
    expect((s as { async?: boolean }).async).toBe(true)
  })
})

describe('valibotCreateSv: factory config', () => {
  it('shares onUnknown policy across schemas', () => {
    const factory = valibotCreateSv({ onUnknown: 'throw' })
    const json    = schema({ host: { type: 'string', format: 'hostname' } }, ['host'])
    expect(() => factory(json).build()).toThrow('hostname')
  })

  it('per-schema onUnknown() overrides factory config', () => {
    const factory = valibotCreateSv({ onUnknown: 'throw' })
    const json    = schema({ host: { type: 'string', format: 'hostname' } }, ['host'])
    expect(() => factory(json).onUnknown('warn').build()).not.toThrow()
  })

  it('check: true warns during build()', () => {
    const warn    = vi.spyOn(console, 'warn').mockImplementation(() => {})
    const factory = valibotCreateSv({ check: true })
    const json    = schema({ host: { type: 'string', format: 'hostname' } }, ['host'])
    factory(json).build()
    expect(warn).toHaveBeenCalledWith(expect.stringContaining('[schemable]'), expect.anything())
  })
})

// ── x-custom-fields check warning ────────────────────────────────────────────

describe('zodSv: x-custom-fields check warning', () => {
  const customJson: ObjectSchema = {
    ...schema({ email: { type: 'string', format: 'email' } }, ['email']),
    'x-custom-fields': ['email_unique'],
  }

  it('warns when check:true and x-custom-fields declared but no refiners', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    zodCreateSv({ check: true })(customJson).build()
    expect(warn).toHaveBeenCalledWith(
      expect.stringContaining('x-custom-fields'),
      expect.arrayContaining(['email_unique']),
    )
    warn.mockRestore()
  })

  it('does not warn when refiners are registered', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    zodCreateSv({ check: true })(customJson).refine(() => {}).build()
    const customWarn = warn.mock.calls.find(c => String(c[0]).includes('x-custom-fields'))
    expect(customWarn).toBeUndefined()
    warn.mockRestore()
  })

  it('does not warn when check is false', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    zodSv(customJson).build()
    expect(warn).not.toHaveBeenCalled()
    warn.mockRestore()
  })
})

describe('valibotSv: x-custom-fields check warning', () => {
  const customJson: ObjectSchema = {
    ...schema({ email: { type: 'string', format: 'email' } }, ['email']),
    'x-custom-fields': ['email_unique'],
  }

  it('warns when check:true and x-custom-fields declared but no refiners', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    valibotCreateSv({ check: true })(customJson).build()
    expect(warn).toHaveBeenCalledWith(
      expect.stringContaining('x-custom-fields'),
      expect.arrayContaining(['email_unique']),
    )
    warn.mockRestore()
  })

  it('does not warn when refiners are registered', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    valibotCreateSv({ check: true })(customJson).refine(() => {}).build()
    const customWarn = warn.mock.calls.find(c => String(c[0]).includes('x-custom-fields'))
    expect(customWarn).toBeUndefined()
    warn.mockRestore()
  })

  it('does not warn when check is false', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    valibotSv(customJson).build()
    expect(warn).not.toHaveBeenCalled()
    warn.mockRestore()
  })
})
