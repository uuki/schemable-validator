/**
 * Detects usage of @deprecated APIs in src/ via the TypeScript compiler's
 * suggestion diagnostics (code 6385). Fails on any new deprecated call
 * before it reaches the IDE or a review.
 *
 * How it works:
 *   TypeScript reports @deprecated usages as getSuggestionDiagnostics() entries
 *   with code 6385. These are visible in IDEs but NOT in `tsc --noEmit` output,
 *   so a dedicated test is the only automated catch.
 *
 * getSuggestionDiagnostics exists on the Program object at runtime but is not
 * part of the public ts.Program interface (it is internal to the compiler).
 * ProgramInternal extends the public type to expose only what this test needs.
 */
import ts from 'typescript'
import { readFileSync } from 'node:fs'
import { resolve, relative } from 'node:path'
import { describe, it, expect } from 'vitest'

interface ProgramInternal extends ts.Program {
  getSuggestionDiagnostics(sourceFile: ts.SourceFile): readonly ts.Diagnostic[]
}

const ROOT = resolve(import.meta.dirname, '..')

function loadProgram(): ProgramInternal {
  const configPath = resolve(ROOT, 'tsconfig.json')
  const configText = readFileSync(configPath, 'utf-8')
  const { config } = ts.parseConfigFileTextToJson(configPath, configText)
  const parsed = ts.parseJsonConfigFileContent(config, ts.sys, ROOT)
  return ts.createProgram(parsed.fileNames, parsed.options) as ProgramInternal
}

interface DeprecatedUsage {
  file:    string
  line:    number
  message: string
}

function collectDeprecated(program: ProgramInternal): DeprecatedUsage[] {
  const results: DeprecatedUsage[] = []

  for (const sf of program.getSourceFiles()) {
    if (!sf.fileName.startsWith(resolve(ROOT, 'src'))) continue

    for (const diag of program.getSuggestionDiagnostics(sf)) {
      if (diag.code !== 6385) continue
      const { line } = sf.getLineAndCharacterOfPosition(diag.start ?? 0)
      results.push({
        file:    relative(ROOT, sf.fileName),
        line:    line + 1,
        message: ts.flattenDiagnosticMessageText(diag.messageText, ' '),
      })
    }
  }

  return results
}

describe('no deprecated API usage in src/', () => {
  it('should have zero @deprecated usages', () => {
    const program  = loadProgram()
    const findings = collectDeprecated(program)

    const report = findings
      .map(f => `  ${f.file}:${f.line}  ${f.message}`)
      .join('\n')

    expect(findings, `Deprecated API usage found:\n${report}`).toHaveLength(0)
  })
})
