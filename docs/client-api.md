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
| [Core validator](./client-reference/core-validator) | `validateObject`, `isAllValid`, `extractErrors` |
| [Result primitives](./client-reference/result) | `ok`, `err`, `isOk`, `isErr`, `map`, `flatMap`, `mapErr`, `getOrElse` |
| [Constraint pipeline](./client-reference/constraint-pipeline) | `constraintsFromSchema`, `composeConstraints`, individual factories, `PATTERN_MAX_INPUT_LENGTH` |
| [Zod adapter](./client-reference/zod-adapter) | `sv`, `createSv`, `ZodRefiner`, `ZodAsyncRefiner`, `toZodSchema`, `checkZodSchema` |
| [Valibot adapter](./client-reference/valibot-adapter) | `sv`, `createSv`, `ValibotRefiner`, `ValibotAsyncRefiner`, `toValibotSchema`, `checkValibotSchema` |
| [Type reference](./client-reference/types) | `ObjectSchema`, `PropertySchema`, `WhenCondition`, `FieldResult`, `FieldState`, `Constraint` |
