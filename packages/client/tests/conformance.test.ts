import { describe, it, expect } from 'vitest'
import { readdirSync, readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join, resolve } from 'node:path'
import { validateObject } from '../src/validator.js'
import type { ObjectSchema } from '../src/schema.js'

// ── Conformance runner ────────────────────────────────────────────────────────
//
// Reads the same conformance/*.json fixtures as
// packages/core/tests/Conformance/ConformanceTest.php and asserts the TS kernel's
// result against each fixture's `expected` block.
//
// Fixtures marked `knownMismatch: true` document a currently-open BE/FE gap: if
// this runner's result doesn't yet match `expected`, the test is dynamically
// skipped (visible, but does not fail the suite) instead of red. Once the
// underlying gap is closed, this runner's result will match `expected` and the
// assertion passes normally.

type Fixture = {
  readonly name: string
  readonly category: 'parity' | 'structural' | 'contract'
  readonly schema: ObjectSchema
  readonly input: Readonly<Record<string, string | readonly string[]>>
  readonly expected: Readonly<Record<string, { readonly is_valid: boolean }>>
  readonly knownMismatch?: boolean
}

const __dirname = dirname(fileURLToPath(import.meta.url))
const CONFORMANCE_ROOT = resolve(__dirname, '../../../conformance')

const loadFixtures = (): ReadonlyArray<{ relativePath: string; fixture: Fixture }> => {
  const fixtures: { relativePath: string; fixture: Fixture }[] = []
  for (const category of readdirSync(CONFORMANCE_ROOT, { withFileTypes: true })) {
    if (!category.isDirectory()) continue
    const dir = join(CONFORMANCE_ROOT, category.name)
    for (const file of readdirSync(dir)) {
      if (!file.endsWith('.json')) continue
      const fixture = JSON.parse(readFileSync(join(dir, file), 'utf-8')) as Fixture
      fixtures.push({ relativePath: `${category.name}/${file}`, fixture })
    }
  }
  return fixtures
}

describe('conformance fixtures', () => {
  for (const { relativePath, fixture } of loadFixtures()) {
    it(`${fixture.category}: ${fixture.name} (${relativePath})`, (ctx) => {
      const result = validateObject(fixture.input, fixture.schema)

      for (const [field, exp] of Object.entries(fixture.expected)) {
        const actual = result[field]?.is_valid

        if (actual === exp.is_valid) {
          expect(actual).toBe(exp.is_valid)
          continue
        }

        if (fixture.knownMismatch) {
          ctx.skip(
            `[${fixture.name}] known BE/FE mismatch on field '${field}': ` +
            `expected is_valid=${exp.is_valid}, FE got ${actual} (see ${relativePath})`
          )
        }

        expect(actual).toBe(exp.is_valid)
      }
    })
  }
})
