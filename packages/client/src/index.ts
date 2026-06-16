// Result — ROP primitives
export { ok, err, isOk, isErr, map, flatMap, mapErr, getOrElse } from './result.js'
export type { Ok, Err, Result } from './result.js'

// Schema types (mirror of SchemaBuilder::toJsonSchema() output)
export type { JsonSchemaType, PropertySchema, ObjectSchema, ConditionalSchema, WhenEntry } from './schema.js'

// JSONLogic evaluator types (for x-when conditions)
export type { JLCondition, JLValue, JLVar } from './jsonLogic.js'
export { applyJsonLogic } from './jsonLogic.js'

// Constraint pipeline (exported for consumers who need custom rules)
export {
  composeConstraints,
  constraintsFromSchema,
  checkType,
  checkMinLength,
  checkMaxLength,
  checkMinimum,
  checkMaximum,
  checkFormat,
  checkPattern,
  checkEnum,
  PATTERN_MAX_INPUT_LENGTH,
} from './constraint.js'
export type { FieldState, Constraint } from './constraint.js'

// Validator
export { validateObject, isAllValid, extractErrors } from './validator.js'
export type { FieldResult, ValidationResult } from './validator.js'
