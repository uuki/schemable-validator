# Examples - Client

`@uuki/schemable-validator-client` を使ったクライアントサイドの実装例です。

> ソースコード: [`packages/client/examples/`](https://github.com/uuki/schemable-validator/tree/v0.9.1/packages/client/examples)

---

## 1. 基本的な使い方

`validateObject` でオブジェクトをスキーマに照合し、`isAllValid` / `extractErrors` で結果を取得します。

<<< ../../../packages/client/examples/01-basic.ts

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/client/examples/01-basic.ts)

---

## 2. fetch との組み合わせ

REST エンドポイントからスキーマを取得し、フォーム送信時にバリデーションを実行します。

:::code-group

<<< ../../../packages/client/examples/02-with-fetch-core.ts [Core]

<<< ../../../packages/client/examples/02-with-fetch.ts [WordPress]

:::

---

## 3. フレームワークバリデーターへの注入

サーバーで定義した JSON Schema を取得して Zod・Valibot・AJV に注入します。PHP 側の定義を変えるだけでクライアント側のルールも自動的に同期されます。

Zod・Valibot はビルトインアダプター経由、AJV は JSON Schema をそのまま受け取ります。

:::code-group

<<< ../../../packages/client/examples/07-with-ajv.ts [AJV]

<<< ../../../packages/client/examples/05-with-zod.ts [Zod]

<<< ../../../packages/client/examples/06-with-valibot.ts [Valibot]

:::

---

## 4. カスタム Constraint の追加

`Constraint`（`FieldState → FieldState` の純関数）を定義し、組み込みバリデーションに追加検証を合成します。

<<< ../../../packages/client/examples/03-custom-constraint.ts

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/client/examples/03-custom-constraint.ts)

---

## 5. Result 型でのチェーン

`validateObject` の結果を `Result` 型でラップし、`flatMap` で成功/失敗を連鎖させます。

<<< ../../../packages/client/examples/04-result-chaining.ts

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/client/examples/04-result-chaining.ts)
