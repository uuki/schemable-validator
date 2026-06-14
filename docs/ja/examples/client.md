# Examples - Client

`@schemable-validator/client` を使ったクライアントサイドの実装例です。

> ソースコード: [`packages/client/examples/`](https://github.com/uuki/schemable-validator/tree/v0.9.1/packages/client/examples)

---

## 1. 基本的な使い方

`validateObject` でオブジェクトをスキーマに照合し、`isAllValid` / `extractErrors` で結果を取得します。

<<< ../../../packages/client/examples/01-basic.ts

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/client/examples/01-basic.ts)

---

## 2. fetch との組み合わせ

REST エンドポイントからスキーマを取得し、フォーム送信時にバリデーションを実行します。

<<< ../../../packages/client/examples/02-with-fetch.ts

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/client/examples/02-with-fetch.ts)

---

## 3. カスタム Constraint の追加

`Constraint`（`FieldState → FieldState` の純関数）を定義し、組み込みバリデーションに追加検証を合成します。

<<< ../../../packages/client/examples/03-custom-constraint.ts

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/client/examples/03-custom-constraint.ts)

---

## 4. Result 型でのチェーン

`validateObject` の結果を `Result` 型でラップし、`flatMap` で成功/失敗を連鎖させます。

<<< ../../../packages/client/examples/04-result-chaining.ts

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/client/examples/04-result-chaining.ts)
