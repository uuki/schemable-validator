# Examples - Client

Client-side implementation examples using `@uuki/schemable-validator-client`.

> Source code: [`packages/client/examples/`](https://github.com/uuki/schemable-validator/tree/v0.9.1/packages/client/examples)

---

## 1. Basic Usage

Match an object against a schema with `validateObject`, then retrieve results using `isAllValid` / `extractErrors`.

<<< ../../packages/client/examples/01-basic.ts

[View on GitHub](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/client/examples/01-basic.ts)

---

## 2. Using with fetch

Fetch a schema from a REST endpoint and run validation on form submission.

:::code-group

<<< ../../packages/client/examples/02-with-fetch-core.ts [Core]

<<< ../../packages/client/examples/02-with-fetch.ts [WordPress]

:::

---

## 3. Adding a Custom Constraint

Define a `Constraint` (a pure function of `FieldState → FieldState`) and compose it with the built-in validation as additional verification.

<<< ../../packages/client/examples/03-custom-constraint.ts

[View on GitHub](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/client/examples/03-custom-constraint.ts)

---

## 4. Chaining with the Result Type

Wrap the result of `validateObject` in the `Result` type and chain success/failure paths with `flatMap`.

<<< ../../packages/client/examples/04-result-chaining.ts

[View on GitHub](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/client/examples/04-result-chaining.ts)
