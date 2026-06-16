/**
 * Minimal JSONLogic evaluator for the x-when subset.
 *
 * Supported operators: ===, !==, >=, <=, >, <, and, or
 * Values: scalar literals + { var: "fieldName" } references.
 *
 * Semantics match JsonLogicEval.php:
 *   - ===, !== compare as strings (form data is always string-typed)
 *   - >=, <=, >, <  compare as numbers via Coercion Contract v1 rules
 *     (hex/octal/binary prefix rejected; leading/trailing whitespace trimmed)
 */

export type JLVar = { readonly var: string }
export type JLValue = string | number | boolean | null | JLVar

export type JLCondition =
  | { readonly '===': readonly [JLValue, JLValue] }
  | { readonly '!==': readonly [JLValue, JLValue] }
  | { readonly '>=':  readonly [JLValue, JLValue] }
  | { readonly '<=':  readonly [JLValue, JLValue] }
  | { readonly '>':   readonly [JLValue, JLValue] }
  | { readonly '<':   readonly [JLValue, JLValue] }
  | { readonly and:   readonly JLCondition[] }
  | { readonly or:    readonly JLCondition[] }

const resolve = (value: JLValue, data: Record<string, unknown>): unknown => {
  if (value !== null && typeof value === 'object' && 'var' in value) {
    return data[(value as JLVar).var] ?? null
  }
  return value
}

const toStr = (value: unknown): string => {
  if (Array.isArray(value)) return (value as unknown[]).map(String).join(',')
  return String(value ?? '')
}

// Coercion Contract v1: trim whitespace, reject hex/octal/binary, return 0 on failure.
const toNum = (value: unknown): number => {
  const s = toStr(value).trim()
  if (s === '') return 0
  if (/^[+-]?0[xXoObB]/.test(s)) return 0
  const n = Number(s)
  return Number.isFinite(n) ? n : 0
}

export const applyJsonLogic = (condition: JLCondition, data: Record<string, unknown>): boolean => {
  const op = (Object.keys(condition)[0] ?? '') as string
  const args = (condition as Record<string, unknown>)[op]

  if (op === 'and') return (args as JLCondition[]).every((c) => applyJsonLogic(c, data))
  if (op === 'or')  return (args as JLCondition[]).some((c)  => applyJsonLogic(c, data))

  const [rawA, rawB] = args as [JLValue, JLValue]
  const a = resolve(rawA, data)
  const b = resolve(rawB, data)

  switch (op) {
    case '===': return toStr(a) === toStr(b)
    case '!==': return toStr(a) !== toStr(b)
    case '>=':  return toNum(a) >= toNum(b)
    case '<=':  return toNum(a) <= toNum(b)
    case '>':   return toNum(a) >  toNum(b)
    case '<':   return toNum(a) <  toNum(b)
    default:    return false
  }
}
