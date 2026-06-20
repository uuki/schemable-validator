# Examples - WordPress

WordPress プラグインとして有効化した環境での実装例です。`schv_*` ヘルパー関数を利用します。

> ソースコード: [`packages/example/wordpress/`](https://github.com/uuki/schemable-validator/tree/v0.9.1/packages/example/wordpress)

---

## 1. 基本的なバリデーション

`schv_validator()` でバリデーターを生成し、`template_redirect` フックでフォーム送信を処理します。

<<< ../../../packages/example/wordpress/01-validate.php

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/wordpress/01-validate.php)

---

## 2. ファイルアップロードのバリデーション

`validateFiles()` で `$_FILES` を検証し、許容する MIME タイプを制限します。

<<< ../../../packages/example/wordpress/02-validate-files.php

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/wordpress/02-validate-files.php)

---

## 3. CSRF トークン保護

`createToken()` で hidden フィールドにトークンを埋め込み、送信時に `checkToken()` で照合します。

<<< ../../../packages/example/wordpress/03-csrf.php

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/wordpress/03-csrf.php)

---

## 4. メールテンプレートレンダリング

`schv_template()` で WP オプションのテンプレートにバリデーション済みデータを差し込みます。

<<< ../../../packages/example/wordpress/04-template.php

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/wordpress/04-template.php)

---

## 5. マルチページフォーム（入力 → 確認 → 完了）

`schv_form()` でセッションにデータを保持し、3ページにまたがるフォームを実装します。

<<< ../../../packages/example/wordpress/05-multipage-form.php

[GitHub で見る](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/wordpress/05-multipage-form.php)

---

## 6. スキーマエディタの定義とコードのマージ

スキーマエディタ（管理画面）で定義したプリミティブフィールドと、GUI では表現できないロジック（ファイルアップロード、条件付き必須、カスタムバリデーション、ドライバ注入）を `mergeJsonSchema()` で組み合わせます。

```php
use SchemableValidator\SV;
use SchemableValidator\Adapters\Captcha\ReCaptchaV3Driver;
use SchemableValidator\Adapters\Native\NativeImageDriver;

// 1. スキーマエディタで作成したスキーマを読み込む（スラッグ: "contact"）
$gui = schv_stored_schema('contact')->toJsonSchema();

// 2. コード側のフィールドと条件を追加してマージ
$schema = SV::object([
  'avatar'       => SV::file(['image/jpeg', 'image/png'], ['maxWidth' => 4096]),
  'company_name' => SV::string()->min(1)->max(200)->optional(),
])->mergeJsonSchema($gui)
  ->when('type', SV::equal('company'), ['company_name']);

// 3. ドライバを指定してバリデーション
$result = $schema
  ->toValidator([
    'imageDriver'   => new NativeImageDriver(),
    'captchaDriver' => new ReCaptchaV3Driver('YOUR_SECRET'),
  ])
  ->validate($_POST)
  ->validateFiles($_FILES)
  ->validateCaptcha()
  ->getResult();
```

GUI で定義したフィールド（`name`、`email`、`type`）と、コードで定義したフィールド（`avatar`、`company_name`）がまとめて検証されます。
同名のフィールドが両方に存在する場合、コード側の定義が優先されます。

マージしたスキーマをクライアント向けに REST エンドポイントとして公開するには以下のようにします。

```php
schv_register_schema('/contact', schv_stored_schema('contact'));
```
