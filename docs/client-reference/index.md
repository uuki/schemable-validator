# Client API Reference

`@uuki/schemable-validator-client` exposes four distinct groups of exports.

| Import path | What you get |
|---|---|
| `@uuki/schemable-validator-client` | `validateObject`, Result primitives, Constraint pipeline, schema types |
| `@uuki/schemable-validator-client/zod` | `sv`, `createSv`, `toZodSchema`, `checkZodSchema`, type exports |
| `@uuki/schemable-validator-client/valibot` | `sv`, `createSv`, `toValibotSchema`, `checkValibotSchema`, type exports |

---

| Page | Contents |
|:--|:--|
| [Core Validator](./core-validator) | `validateObject`, `isAllValid`, `extractErrors` |
| [Result Primitives](./result) | `ok`, `err`, `isOk`, `isErr`, `map`, `flatMap`, `mapErr`, `getOrElse` |
| [Constraint Pipeline](./constraint-pipeline) | `constraintsFromSchema`, `composeConstraints`, `applyTransform`, constraint factories, `PATTERN_MAX_INPUT_LENGTH`, `DEFAULT_MESSAGES` |
| [Zod Adapter](./zod-adapter) | `sv`, `createSv`, `ZodRefiner`, `ZodAsyncRefiner`, `toZodSchema`, `checkZodSchema` |
| [Valibot Adapter](./valibot-adapter) | `sv`, `createSv`, `ValibotRefiner`, `ValibotAsyncRefiner`, `toValibotSchema`, `checkValibotSchema` |
| [Type Reference](./types) | `ObjectSchema`, `PropertySchema`, `WhenEntry`, `JLCondition`, `JLValue`, `JLVar`, `UiSchema`, `UiSchemaControl`, `FieldResult`, `FieldState`, `Constraint` |
