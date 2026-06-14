# Examples — WordPress

WordPress プラグインとして有効化した環境での実装例です。`schv_*` ヘルパー関数を利用します。

> ソースコード: [`packages/example/wordpress/`](https://github.com/uuki/schemable-validator/tree/v0.9.1/packages/example/wordpress)

---

## 1. 基本的なバリデーション

`schv_validator()` でバリデーターを生成し、`template_redirect` フックでフォーム送信を処理します。

<<< ../../packages/example/wordpress/01-validate.php

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/wordpress/01-validate.php)

---

## 2. ファイルアップロードのバリデーション

`validateFiles()` で `$_FILES` を検証し、許容する MIME タイプを制限します。

<<< ../../packages/example/wordpress/02-validate-files.php

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/wordpress/02-validate-files.php)

---

## 3. CSRF トークン保護

`createToken()` で hidden フィールドにトークンを埋め込み、送信時に `checkToken()` で照合します。

<<< ../../packages/example/wordpress/03-csrf.php

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/wordpress/03-csrf.php)

---

## 4. メールテンプレートレンダリング

`schv_template()` で WP オプションのテンプレートにバリデーション済みデータを差し込みます。

<<< ../../packages/example/wordpress/04-template.php

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/wordpress/04-template.php)

---

## 5. マルチページフォーム（入力 → 確認 → 完了）

`schv_form()` でセッションにデータを保持し、3ページにまたがるフォームを実装します。

<<< ../../packages/example/wordpress/05-multipage-form.php

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/wordpress/05-multipage-form.php)
