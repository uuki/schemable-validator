import { describe, it, expect } from 'vitest'
import {
  checkFormat,
  checkPattern,
  checkMinLength,
  checkMaxLength,
  checkMinimum,
  checkMaximum,
  checkEnum,
  checkType,
  constraintsFromSchema,
  PATTERN_MAX_INPUT_LENGTH,
} from '../src/constraint.js'

const state = (value: string) => ({ value, errors: [] as readonly string[] })

// ── checkType ────────────────────────────────────────────────────────────────

describe('checkType', () => {
  it('passes integer for whole numbers', () => {
    expect(checkType('integer')(state('42')).errors).toHaveLength(0)
  })
  it('fails integer for float strings', () => {
    expect(checkType('integer')(state('3.14')).errors.length).toBeGreaterThan(0)
  })
  it('passes number for float strings', () => {
    expect(checkType('number')(state('3.14')).errors).toHaveLength(0)
  })
  it('fails number for non-numeric strings', () => {
    expect(checkType('number')(state('hello')).errors.length).toBeGreaterThan(0)
  })
  it('passes boolean for accepted literals (lowercase)', () => {
    for (const v of ['true', 'false', '1', '0', 'on', 'off', 'yes', 'no']) {
      expect(checkType('boolean')(state(v)).errors).toHaveLength(0)
    }
  })
  it('passes boolean for mixed-case literals', () => {
    for (const v of ['TRUE', 'True', 'FALSE', 'YES', 'Yes', 'NO', 'ON', 'OFF']) {
      expect(checkType('boolean')(state(v)).errors).toHaveLength(0)
    }
  })
  it('rejects boolean with leading or trailing whitespace', () => {
    for (const v of [' true', 'true ', ' true ', ' 1 ', '\ttrue']) {
      expect(checkType('boolean')(state(v)).errors.length).toBeGreaterThan(0)
    }
  })
  it('fails boolean for arbitrary strings', () => {
    expect(checkType('boolean')(state('yes_maybe')).errors.length).toBeGreaterThan(0)
  })
  it('passes integer for whitespace-padded whole numbers', () => {
    expect(checkType('integer')(state(' 42 ')).errors).toHaveLength(0)
    expect(checkType('integer')(state('\t5\n')).errors).toHaveLength(0)
  })
  it('passes number for whitespace-padded float strings', () => {
    expect(checkType('number')(state(' 3.14 ')).errors).toHaveLength(0)
  })
  it('rejects integer and number for hex/octal/binary literals', () => {
    for (const v of ['0x10', '0X10', '0o7', '0O7', '0b101', '0B101']) {
      expect(checkType('integer')(state(v)).errors.length).toBeGreaterThan(0)
      expect(checkType('number')(state(v)).errors.length).toBeGreaterThan(0)
    }
  })
  it('rejects integer and number for Infinity and NaN', () => {
    for (const v of ['Infinity', '-Infinity', 'NaN']) {
      expect(checkType('integer')(state(v)).errors.length).toBeGreaterThan(0)
      expect(checkType('number')(state(v)).errors.length).toBeGreaterThan(0)
    }
  })
  it('accepts null type in array (string | null)', () => {
    expect(checkType(['string', 'null'])(state('hello')).errors).toHaveLength(0)
  })
  it('returns state unchanged when type is undefined', () => {
    const s = state('anything')
    expect(checkType(undefined)(s)).toBe(s)
  })
})

// ── checkMinLength / checkMaxLength ──────────────────────────────────────────

describe('checkMinLength', () => {
  it('passes when value meets minimum', () => {
    expect(checkMinLength(3)(state('abc')).errors).toHaveLength(0)
  })
  it('fails when value is too short', () => {
    expect(checkMinLength(3)(state('ab')).errors.length).toBeGreaterThan(0)
  })
  it('uses singular "character" at min=1', () => {
    const errors = checkMinLength(1)(state('')).errors
    expect(errors[0]).toMatch(/1 character\b/)
  })
})

describe('checkMaxLength', () => {
  it('passes when value is within limit', () => {
    expect(checkMaxLength(5)(state('abc')).errors).toHaveLength(0)
  })
  it('fails when value exceeds limit', () => {
    expect(checkMaxLength(2)(state('abc')).errors.length).toBeGreaterThan(0)
  })
  it('counts an astral-plane codepoint as 1 character, not 2', () => {
    // U+1F600 "😀" is a surrogate pair in UTF-16/JS .length, but a single codepoint.
    expect(checkMaxLength(1)(state('😀')).errors).toHaveLength(0)
  })
})

// ── checkMinimum / checkMaximum ──────────────────────────────────────────────

describe('checkMinimum', () => {
  it('passes at the boundary', () => {
    expect(checkMinimum(5)(state('5')).errors).toHaveLength(0)
  })
  it('fails below boundary', () => {
    expect(checkMinimum(5)(state('4')).errors.length).toBeGreaterThan(0)
  })
  it('passes for whitespace-padded value at boundary', () => {
    expect(checkMinimum(5)(state(' 5 ')).errors).toHaveLength(0)
  })
  it('rejects hex literal that Number() would accept as in-range', () => {
    // Number('0x10') === 16 >= 5, but parseDecimalInput returns NaN → rejects
    expect(checkMinimum(5)(state('0x10')).errors.length).toBeGreaterThan(0)
  })
})

describe('checkMaximum', () => {
  it('passes at the boundary', () => {
    expect(checkMaximum(10)(state('10')).errors).toHaveLength(0)
  })
  it('fails above boundary', () => {
    expect(checkMaximum(10)(state('11')).errors.length).toBeGreaterThan(0)
  })
  it('passes for whitespace-padded value at boundary', () => {
    expect(checkMaximum(10)(state(' 10 ')).errors).toHaveLength(0)
  })
  it('rejects hex literal that Number() would accept as in-range', () => {
    // Number('0x5') === 5 <= 10, but parseDecimalInput returns NaN → rejects
    expect(checkMaximum(10)(state('0x5')).errors.length).toBeGreaterThan(0)
  })
})

// ── checkEnum ────────────────────────────────────────────────────────────────

describe('checkEnum', () => {
  it('passes for a value in the list', () => {
    expect(checkEnum(['a', 'b', 'c'])(state('b')).errors).toHaveLength(0)
  })
  it('fails for a value not in the list', () => {
    expect(checkEnum(['a', 'b'])(state('c')).errors.length).toBeGreaterThan(0)
  })
})

// ── checkPattern ─────────────────────────────────────────────────────────────

describe('checkPattern', () => {
  it('passes when pattern matches', () => {
    expect(checkPattern('^[a-z]+$')(state('abc')).errors).toHaveLength(0)
  })
  it('fails when pattern does not match', () => {
    expect(checkPattern('^[a-z]+$')(state('ABC')).errors.length).toBeGreaterThan(0)
  })
  it('does not throw for invalid regex', () => {
    expect(() => checkPattern('(invalid')(state('x'))).not.toThrow()
  })
  it('skips evaluation when input exceeds default PATTERN_MAX_INPUT_LENGTH', () => {
    const longInput = 'a'.repeat(PATTERN_MAX_INPUT_LENGTH + 1)
    // Pattern that would reject any 'a'-only string — but must be skipped
    const result = checkPattern('^[b]+$')(state(longInput))
    expect(result.errors).toHaveLength(0)
  })
  it('evaluates normally when input is exactly at the limit', () => {
    const atLimit = 'A'.repeat(PATTERN_MAX_INPUT_LENGTH)
    const result = checkPattern('^[a-z]+$')(state(atLimit))
    expect(result.errors.length).toBeGreaterThan(0)
  })
  it('respects a custom maxInputLength override', () => {
    const input = 'a'.repeat(10)
    // limit = 5: input of 10 chars should be skipped
    const skipped = checkPattern('^[b]+$', 5)(state(input))
    expect(skipped.errors).toHaveLength(0)
    // limit = 20: input of 10 chars should be evaluated
    const evaluated = checkPattern('^[b]+$', 20)(state(input))
    expect(evaluated.errors.length).toBeGreaterThan(0)
  })
})

// ── checkFormat ──────────────────────────────────────────────────────────────

describe('checkFormat: email', () => {
  it.each([
    'user@example.com',
    'a+b@sub.domain.org',
  ])('accepts %s', (v) => {
    expect(checkFormat('email')(state(v)).errors).toHaveLength(0)
  })
  it.each([
    'not-an-email',
    '@nodomain',
    'missing@',
  ])('rejects %s', (v) => {
    expect(checkFormat('email')(state(v)).errors.length).toBeGreaterThan(0)
  })
})

describe('checkFormat: date', () => {
  it.each(['2024-01-15', '2000-12-31', '2024-02-29'])('accepts %s', (v) => {
    expect(checkFormat('date')(state(v)).errors).toHaveLength(0)
  })
  it.each([
    '2024-1-5',
    '01-15-2024',
    'not-a-date',
    '2026-02-30',
    '2024-04-31',
    '2023-02-29',
  ])('rejects %s', (v) => {
    expect(checkFormat('date')(state(v)).errors.length).toBeGreaterThan(0)
  })
})

describe('checkFormat: date-time', () => {
  it.each([
    '2024-01-15T12:00:00Z',
    '2024-01-15T12:00:00+09:00',
    '2024-01-15T12:00:00.123Z',
    '2024-02-29T12:00:00Z',
    '2024-01-15T23:59:60Z',
  ])('accepts %s', (v) => {
    expect(checkFormat('date-time')(state(v)).errors).toHaveLength(0)
  })
  it.each([
    '2024-01-15',
    '2024-01-15 12:00:00',
    'not-a-datetime',
    '2026-02-30T12:00:00Z',
    '2023-02-29T00:00:00Z',
    '2024-01-15T12:00:61Z',
  ])('rejects %s', (v) => {
    expect(checkFormat('date-time')(state(v)).errors.length).toBeGreaterThan(0)
  })
})

describe('checkFormat: time', () => {
  it.each(['12:30:00', '00:00:00', '23:59:59', '23:59:60'])('accepts %s', (v) => {
    expect(checkFormat('time')(state(v)).errors).toHaveLength(0)
  })
  it.each(['25:00:00', '12:60:00', '12:30:61', 'not-a-time'])('rejects %s', (v) => {
    expect(checkFormat('time')(state(v)).errors.length).toBeGreaterThan(0)
  })
})

describe('checkFormat: uuid', () => {
  it.each([
    'f47ac10b-58cc-4372-a567-0e02b2c3d479',
    '00000000-0000-0000-0000-000000000000',
  ])('accepts %s', (v) => {
    expect(checkFormat('uuid')(state(v)).errors).toHaveLength(0)
  })
  it.each(['not-a-uuid', 'f47ac10b-58cc-4372-a567'])('rejects %s', (v) => {
    expect(checkFormat('uuid')(state(v)).errors.length).toBeGreaterThan(0)
  })
})

describe('checkFormat: ipv4', () => {
  it.each(['192.168.1.1', '0.0.0.0', '255.255.255.255'])('accepts %s', (v) => {
    expect(checkFormat('ipv4')(state(v)).errors).toHaveLength(0)
  })
  it.each(['256.0.0.1', '192.168.1', 'not-an-ip'])('rejects %s', (v) => {
    expect(checkFormat('ipv4')(state(v)).errors.length).toBeGreaterThan(0)
  })
})

describe('checkFormat: ipv6', () => {
  it.each([
    '2001:0db8:0000:0000:0000:0000:0000:0001',
    '2001:db8::1',
    '::1',
    '::',
    'fe80::1',
    '2001:db8:85a3::8a2e:370:7334',
  ])('accepts %s', (v) => {
    expect(checkFormat('ipv6')(state(v)).errors).toHaveLength(0)
  })
  it.each([
    'not-an-ipv6',
    '192.168.1.1',
    'gggg::1',
  ])('rejects %s', (v) => {
    expect(checkFormat('ipv6')(state(v)).errors.length).toBeGreaterThan(0)
  })
})

describe('checkFormat: hostname', () => {
  it.each(['example.com', 'sub.example.co.jp', 'my-host.net'])('accepts %s', (v) => {
    expect(checkFormat('hostname')(state(v)).errors).toHaveLength(0)
  })
  it.each(['not a hostname', '-invalid.com', ''])('rejects %s', (v) => {
    expect(checkFormat('hostname')(state(v)).errors.length).toBeGreaterThan(0)
  })
})

describe('checkFormat: unknown format', () => {
  it('returns state unchanged for unknown format keys', () => {
    const s = state('anything')
    const result = checkFormat('nonexistent-format')(s)
    expect(result.errors).toHaveLength(0)
  })
})

// ── Injection / invisible character attacks ────────────────────────────────────
//
// These tests verify that injection-capable or visually-hidden characters in
// user input are correctly handled by the constraint pipeline.

describe('checkFormat: injection and invisible characters', () => {
  // Email format
  it('rejects email with null byte', () => {
    expect(checkFormat('email')(state('user\x00@example.com')).errors.length).toBeGreaterThan(0)
  })
  it('rejects email with CRLF injection', () => {
    expect(checkFormat('email')(state('user@example.com\r\nX-Injected: evil')).errors.length).toBeGreaterThan(0)
  })
  it('rejects email with LF only', () => {
    expect(checkFormat('email')(state('user@example.com\nX-Injected: evil')).errors.length).toBeGreaterThan(0)
  })
  it('rejects email with Unicode line separator U+2028', () => {
    // U+2028 interpreted as line break in JS contexts; email format regex rejects it
    expect(checkFormat('email')(state('user@example.com ')).errors.length).toBeGreaterThan(0)
  })
  it('rejects email with zero-width space U+200B', () => {
    // Zero-width space creates visually identical but distinct addresses
    expect(checkFormat('email')(state('user​@example.com')).errors.length).toBeGreaterThan(0)
  })

  // URI format
  it('rejects uri with CRLF injection', () => {
    expect(checkFormat('uri')(state('https://example.com/\r\nX-Injected: evil')).errors.length).toBeGreaterThan(0)
  })
  it('rejects uri with null byte', () => {
    expect(checkFormat('uri')(state('https://example.com/\x00evil')).errors.length).toBeGreaterThan(0)
  })
})

// ── errorMessage override (Step 1-a) ─────────────────────────────────────────

describe('constraintsFromSchema: errorMessage override', () => {
  it('uses custom type message for integer error', () => {
    const result = constraintsFromSchema({
      type: 'integer',
      errorMessage: { type: '整数で入力してください' },
    })({ value: 'abc', errors: [] })
    expect(result.errors).toEqual(['整数で入力してください'])
  })

  it('uses custom type message for number error', () => {
    const result = constraintsFromSchema({
      type: 'number',
      errorMessage: { type: '数値で入力してください' },
    })({ value: 'abc', errors: [] })
    expect(result.errors).toEqual(['数値で入力してください'])
  })

  it('uses custom type message for boolean error', () => {
    const result = constraintsFromSchema({
      type: 'boolean',
      errorMessage: { type: 'true か false を入力してください' },
    })({ value: 'maybe', errors: [] })
    expect(result.errors).toEqual(['true か false を入力してください'])
  })

  it('uses custom format message for format error', () => {
    const result = constraintsFromSchema({
      type: 'string',
      format: 'email',
      errorMessage: { format: '有効なメールアドレスを入力してください' },
    })({ value: 'not-an-email', errors: [] })
    expect(result.errors).toEqual(['有効なメールアドレスを入力してください'])
  })

  it('uses custom minLength message', () => {
    const result = constraintsFromSchema({
      type: 'string',
      minLength: 5,
      errorMessage: { minLength: '5文字以上で入力してください' },
    })({ value: 'abc', errors: [] })
    expect(result.errors).toEqual(['5文字以上で入力してください'])
  })

  it('uses custom maxLength message', () => {
    const result = constraintsFromSchema({
      type: 'string',
      maxLength: 3,
      errorMessage: { maxLength: '3文字以内で入力してください' },
    })({ value: 'abcdef', errors: [] })
    expect(result.errors).toEqual(['3文字以内で入力してください'])
  })

  it('uses custom minimum message', () => {
    const result = constraintsFromSchema({
      type: 'integer',
      minimum: 10,
      errorMessage: { minimum: '10以上で入力してください' },
    })({ value: '5', errors: [] })
    expect(result.errors).toEqual(['10以上で入力してください'])
  })

  it('uses custom maximum message', () => {
    const result = constraintsFromSchema({
      type: 'integer',
      maximum: 100,
      errorMessage: { maximum: '100以下で入力してください' },
    })({ value: '200', errors: [] })
    expect(result.errors).toEqual(['100以下で入力してください'])
  })

  it('falls back to default message when errorMessage absent', () => {
    const result = constraintsFromSchema({ type: 'integer' })({ value: 'abc', errors: [] })
    expect(result.errors).toEqual(['must be an integer'])
  })

  it('falls back to default when errorMessage does not include the triggered key', () => {
    const result = constraintsFromSchema({
      type: 'string',
      format: 'email',
      errorMessage: { minLength: 'something else' },
    })({ value: 'not-an-email', errors: [] })
    expect(result.errors).toEqual(['must be a valid email'])
  })

  it('does not affect valid input', () => {
    const result = constraintsFromSchema({
      type: 'integer',
      errorMessage: { type: 'カスタムエラー' },
    })({ value: '42', errors: [] })
    expect(result.errors).toHaveLength(0)
  })
})

describe('checkPattern: injection and invisible characters', () => {
  it('rejects input with null byte when pattern requires printable chars', () => {
    expect(checkPattern('^[\\w]+$')(state('hello\x00world')).errors.length).toBeGreaterThan(0)
  })

  it('rejects input with CRLF when pattern requires no newline', () => {
    expect(checkPattern('^[^\\r\\n]+$')(state('hello\r\nworld')).errors.length).toBeGreaterThan(0)
  })

  it('passes input with CRLF when pattern uses dotAll via workaround', () => {
    // [\s\S]+ matches everything including CR/LF — document that the pattern controls behaviour
    expect(checkPattern('^[\\s\\S]+$')(state('hello\r\nworld')).errors).toHaveLength(0)
  })

  it('handles null byte in input without throwing', () => {
    expect(() => checkPattern('^[a-z]+$')(state('a\x00b'))).not.toThrow()
  })

  it('handles Unicode line separator U+2028 in input without throwing', () => {
    // U+2028 is 3 UTF-16 code units but one logical char; ensure no RegExp crash
    expect(() => checkPattern('^[a-z]+$')(state('abcdef'))).not.toThrow()
  })

  it('handles zero-width space U+200B without throwing', () => {
    expect(() => checkPattern('^[a-z]+$')(state('abc​def'))).not.toThrow()
  })

  it('backslash sequences in pattern compile correctly', () => {
    // \\d, \\w, \\s are standard regex tokens; verify they work with the u flag
    expect(checkPattern('^\\d{3}-\\d{4}$')(state('123-4567')).errors).toHaveLength(0)
    expect(checkPattern('^\\d{3}-\\d{4}$')(state('abc-defg')).errors.length).toBeGreaterThan(0)
  })

  it('Unicode property escapes work with u flag', () => {
    // \\p{L} requires the u flag; checkPattern always compiles with u
    expect(checkPattern('^\\p{L}+$')(state('hello')).errors).toHaveLength(0)
    expect(checkPattern('^\\p{L}+$')(state('123')).errors.length).toBeGreaterThan(0)
  })
})
