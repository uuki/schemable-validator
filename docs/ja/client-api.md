# クライアント API リファレンス

`@uuki/schemable-validator-client` は4つのエクスポートグループを持ちます。

| インポートパス | 内容 |
|---|---|
| `@uuki/schemable-validator-client` | `validateObject`、Result プリミティブ、Constraint パイプライン、スキーマ型 |
| `@uuki/schemable-validator-client/zod` | `sv`、`createSv`、`toZodSchema`、`checkZodSchema`、型エクスポート |
| `@uuki/schemable-validator-client/valibot` | `sv`、`createSv`、`toValibotSchema`、`checkValibotSchema`、型エクスポート |

---

| ページ | 内容 |
|:--|:--|
| [コアバリデーター](./client-reference/core-validator) | `validateObject`、`isAllValid`、`extractErrors` |
| [Result プリミティブ](./client-reference/result) | `ok`、`err`、`isOk`、`isErr`、`map`、`flatMap`、`mapErr`、`getOrElse` |
| [Constraint パイプライン](./client-reference/constraint-pipeline) | `constraintsFromSchema`、`composeConstraints`、各 Constraint ファクトリー、`PATTERN_MAX_INPUT_LENGTH` |
| [Zod アダプター](./client-reference/zod-adapter) | `sv`、`createSv`、`ZodRefiner`、`ZodAsyncRefiner`、`toZodSchema`、`checkZodSchema` |
| [Valibot アダプター](./client-reference/valibot-adapter) | `sv`、`createSv`、`ValibotRefiner`、`ValibotAsyncRefiner`、`toValibotSchema`、`checkValibotSchema` |
| [型リファレンス](./client-reference/types) | `ObjectSchema`、`PropertySchema`、`WhenCondition`、`FieldResult`、`FieldState`、`Constraint` |
