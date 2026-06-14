# Examples - Core

フレームワーク非依存のコアライブラリを使った実装例です。

> ソースコード: [`packages/example/core/`](https://github.com/uuki/schemable-validator/tree/v0.9.1/packages/example/core)

---

## 1. 基本的なバリデーション

フィールドスキーマを定義し、`Validator` で入力値を検証します。

<<< ../../packages/example/core/01-validate.php

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/core/01-validate.php)

---

## 2. ファイルアップロードのバリデーション

`validateFiles()` を使い、アップロードされたファイルの拡張子・エラーコードを検証します。

<<< ../../packages/example/core/02-validate-files.php

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/core/02-validate-files.php)

---

## 3. reCAPTCHA v3 バリデーション

`validateReCaptcha()` をメソッドチェーンに組み込み、スコア閾値と action 名を検証します。

<<< ../../packages/example/core/03-recaptcha.php

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/core/03-recaptcha.php)

---

## 4. CSRF トークン

`createToken()` でトークンを生成し、`checkToken()` でフォーム送信時に照合します。

<<< ../../packages/example/core/04-csrf-token.php

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/core/04-csrf-token.php)

---

## 5. テンプレートレンダリング

`Template` クラスでセッションの検証済みデータをメール本文に差し込みます。

<<< ../../packages/example/core/05-template.php

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/core/05-template.php)
