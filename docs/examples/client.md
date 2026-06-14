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

## 3. Integrating with Framework Validators

Fetch the server-defined JSON Schema and inject it into Zod, Valibot, or AJV. Client-side rules stay in sync with the PHP definition automatically.

Zod and Valibot use the built-in adapters; AJV accepts the JSON Schema output directly.

:::code-group

<<< ../../packages/client/examples/07-with-ajv.ts [AJV]

<<< ../../packages/client/examples/05-with-zod.ts [Zod]

<<< ../../packages/client/examples/06-with-valibot.ts [Valibot]

:::

---

## 4. Adding a Custom Constraint

Define a `Constraint` (a pure function of `FieldState → FieldState`) and compose it with the built-in validation as additional verification.

<<< ../../packages/client/examples/03-custom-constraint.ts

[View on GitHub](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/client/examples/03-custom-constraint.ts)

---

## 5. Chaining with the Result Type

Wrap the result of `validateObject` in the `Result` type and chain success/failure paths with `flatMap`.

<<< ../../packages/client/examples/04-result-chaining.ts

[View on GitHub](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/client/examples/04-result-chaining.ts)
