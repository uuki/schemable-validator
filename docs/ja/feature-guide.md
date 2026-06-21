# 機能ガイド

このページでは `Validator` クラス、エラーメッセージ、セキュリティ（CSRF、CAPTCHA）、セッション管理、`Template` ヘルパーについて説明します。
スキーマ定義 API は [SchemaBuilder](./schema-builder.md)、ローカライズは [MessageDict](./message-dict.md) を参照してください。

## Validator

フィールドスキーマに対して入力値を検証する中核クラスです。テキスト・ファイル・CAPTCHA の検証をメソッドチェーンで組み合わせられます。

### 1. インスタンス化

::: code-group

```php [Core]
use SchemableValidator\Orchestration\Validator;

$validator = new Validator($schema);
```

```php [WordPress]
$validator = schv_validator($schema);
```

:::

### 2. スキーマ定義

スキーマは `フィールド名 => バリデーションルール` の連想配列で定義します。推奨は SchemaBuilder API（`SV::string()`、`SV::object()` など）で、外部依存なしで動作します。

`name` フィールドを例に取ると:

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name' => SV::string()->min(2)->max(50),
]);
```

`SV::string()->min(2)->max(50)` は「文字列型かつ 2〜50 文字」を意味します。複数の制約はメソッドチェーンで結合できます。

| 値 | 結果 | 理由 |
|:--|:--|:--|
| `'Alice'` | ✓ 通る | 文字列・5文字 |
| `'A'` | ✗ 弾く | 1文字（最小 2 文字を下回る） |
| `''` | ✗ 弾く | 0文字 |
| `123` | ✗ 弾く | 文字列でない |

よくあるフィールドを定義した例:

```php
$schema = SV::object([
  'name'  => SV::string()->min(2)->max(50),
  'email' => SV::string()->email(),
  'tel'   => SV::string()->pattern('^(0\d{9,10}|0\d{1,4}-\d{1,4}-\d{3,4})$')->optional(),
  'type'  => SV::enum(['general', 'support', 'sales', 'other']),
  'body'  => SV::string()->min(10),
]);
```

::: tip Respect スキーマの利用
オプションの `respect/validation` パッケージをインストールすると、Respect ルールも直接使えます（例: `'name' => v::stringType()->length(2, 50)`）。SchemaBuilder 内では `SV::respect(v::...)` でラップしてください。
:::

通る例・弾く例:

```php
// ✓ すべて通る
$data = [
  'name'  => 'Alice',
  'email' => 'alice@example.com',
  'tel'   => '090-1234-5678',
  'type'  => 'general',
  'body'  => 'お世話になっております。〜よろしくお願いいたします。',
];

// ✗ すべて弾く
$data = [
  'name'  => 'A',            // 1文字
  'email' => 'not-an-email', // 形式不正
  'tel'   => '12345',        // パターン不一致
  'type'  => 'unknown',      // 許可外の値
  'body'  => '短い',         // 10文字未満
];
```

### 3. フィールドの検証

`validate()` に入力配列を渡し、`getResult()` で結果を取得します。入力値は自動的にサニタイズ（`strip_tags` + `htmlspecialchars`）されます。

```php
$result = $validator->validate($_POST)->getResult();
```

上記のスキーマで `name` が通り `email` が弾かれた場合の結果構造:

```php
[
  'name' => [
    'value'    => 'Alice',
    'is_valid' => true,
    'errors'   => null,
  ],
  'email' => [
    'value'    => 'not-an-email',
    'is_valid' => false,
    'errors'   => 'must be a valid email',
  ],
  'tel' => [
    'value'    => '090-1234-5678',
    'is_valid' => true,
    'errors'   => null,
  ],
  // 以下同形式で続く
]
```

すべてのフィールドが有効かどうかを確認する:

```php
$all_valid = array_reduce($result, fn($carry, $field) => $carry && $field['is_valid'], true);
```

### 4. 高度な検証

ファイルのアップロード検証には `validateFiles()` を使います。

```php
// $_FILES をそのまま渡す
$result = $validator->validateFiles($_FILES)->getResult();

// $_FILES 以外の配列を渡す場合
$result = $validator->validateFiles($data, ['native_files' => false])->getResult();
```

`SV::file()`（内部で `NativeFileValidator` を使用）で許可する MIME タイプを制限できます:

```php
use SchemableValidator\SV;

$schema = SV::object([
  'file' => SV::file(['image/jpeg', 'image/png']),
]);
```

::: info レガシー
`FileExtension` ルールクラスは引き続き動作しますが、レガシーとみなされます。新規コードでは `SV::file()` を推奨します。
:::

::: tip
住所検証など、独自ルールの定義に類する高度な利用については [Custom Validation](/ja/custom-validation) を参照してください。依存なしの一回限りのルールには `SV::custom(callable)` をエスケープハッチとして使えます。

注意: `creditCard` および `postalCode` ルールは **@deprecated** であり、`Adapters\Respect\RespectRules` に移動されました。
:::

### メソッドチェーン

```php
$result = $validator
  ->validate($_POST)
  ->validateFiles($_FILES)
  ->validateCaptcha()
  ->getResult();
```

---

## SchemaBuilder

`SchemaBuilder` はスキーマ定義の推奨方法です。
同一の定義から、サーバーサイドの `Validator`（`toValidator()` 経由）とフロントエンド向け JSON Schema（`toJson()` 経由）の両方を生成できます。

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name'  => SV::string()->min(2)->max(50),
  'email' => SV::string()->email(),
  'type'  => SV::enum(['general', 'support', 'other']),
]);

// サーバーサイド検証
$result = $schema->toValidator()->validate($_POST)->validateFiles($_FILES)->getResult();

// フロントエンド向け JSON Schema エクスポート
echo $schema->toJson();
```

### クライアント出力からフィールドを除外する

`.serverOnly()` を付けたフィールドは、サーバー側では通常どおり検証されますが、クライアントに送信される JSON Schema 出力からは除外されます。
`properties`、`required`、`x-unmapped-fields` のいずれにも含まれません。

```php
$schema = SV::object([
  'email'       => SV::string()->email(),
  'risk_score'  => SV::integer()->min(0)->max(100)->serverOnly(),
]);

echo $schema->toJson();
// risk_score は含まれません — クライアントからは見えません

$schema->toValidator()->validate($data)->getResult();
// email と risk_score の両方を検証します
```

::: tip
フィールド型リファレンス、`.nullable()`、`.optional()`、`.serverOnly()`、条件付き必須（`.when()`）、`x-unmapped-fields`、WordPress REST エンドポイント登録については [SchemaBuilder](./schema-builder.md) を参照してください。
:::

---

## エラーメッセージ

バリデーション結果に含まれるエラーメッセージの確認方法と、ロケールに合わせたカスタマイズ方法を説明します。

### エラーメッセージの取得

`getResult()` の各フィールドに `errors` キーが含まれます。値は検証が通った場合 `null`、失敗した場合はメッセージ文字列です。デフォルトは DefaultMessages カタログ（エンジン中立）の英語メッセージです。

```php
$result = $validator->validate($_POST)->getResult();

foreach ($result as $field => $state) {
  if (!$state['is_valid']) {
    echo $field . ': ' . $state['errors'];
  }
}
```

複数のルールが失敗した場合、エラーは `"\n"` で結合された文字列として返されます。

```php
// name が string と minLength の両方に失敗した場合
$result['name']['errors'];
// → 'must be a string
//    must be at least 2 characters'
```

### ルールへの直接指定

フィールドの `errorMessage()` メソッドを使い、`{var}` 補間でメッセージを上書きできます。

```php
$schema = SV::object([
  'email' => SV::string()->email()->errorMessage('有効なメールアドレスを入力してください'),
  'name'  => SV::string()->min(2)->max(50)
               ->errorMessage('{field}は{min}〜{max}文字で入力してください'),
]);
```

利用可能なプレースホルダーは制約に依存します: `{field}`、`{min}`、`{max}`、`{pattern}` など。

::: info
`errorMessage()` はフィールド全体に単一のメッセージを適用します。ルール単位で個別に制御したい場合は MessageDict を使ってください。
:::

### 多言語化

`MessageDict` を使うと、フィールド×ルール単位でエラーメッセージを定義し、日本語プリセットをまとめて適用できます。

```php
use SchemableValidator\SV;
use SchemableValidator\I18n\MessageDict;

$result = SV::object([
  'name'  => SV::string()->min(2)->max(50),
  'email' => SV::string()->email(),
])->withMessages(MessageDict::ja([
  'email' => 'メールアドレスが正しくありません',
]))->toValidator()->validate($_POST)->getResult();
```

::: tip
ロケールプリセット、ルール単位キー、プレースホルダー補間（`{min}`、`{max}`）、解決優先順位、WordPress フィルター、Respect ルール ID からの移行については [MessageDict](./message-dict.md) を参照してください。
:::

---

## セキュリティ

CSRF トークン管理、CAPTCHA 検証、フォームセキュリティのベストプラクティスをまとめます。

### CSRF トークン

`Validator` に内蔵されたトークン生成・照合機能です。フォームごとにスコープされたトークンをセッションに保存し、送信時に照合することでリクエスト偽造を防ぎます。

```php
// フォーム表示時: トークンを生成してセッションに保存
$token = $validator->createToken();

// フォーム送信時: トークンを検証
$is_valid = $validator->checkToken($_POST['schv_csrf_token'] ?? '');
```

フォーム内に hidden フィールドとして埋め込む:

```html
<input type="hidden" name="schv_csrf_token" value="<?php echo esc_attr($token); ?>">
```

### CAPTCHA

`toValidator()` に `CaptchaDriver` を注入し、`validateCaptcha()` を呼び出します。
組み込みの実装として `ReCaptchaV3Driver`、`HCaptchaDriver`、`TurnstileDriver` の 3 つが提供されています。

```php
use SchemableValidator\Adapters\Captcha\ReCaptchaV3Driver;

$validator = $schema->toValidator([], [
  'captchaDriver' => new ReCaptchaV3Driver('YOUR_SECRET'),
]);

$result = $validator
  ->validate($_POST)          // g-recaptcha-response / h-captcha-response / cf-turnstile-response を読み取る
  ->validateCaptcha([
    'action' => 'contact',    // action 検証はオプション（reCAPTCHA v3 のみ）
  ])
  ->getResult();
```

結果は `$result['captcha']` に書き込まれます。

```json
{ "value": 0.9, "is_valid": true, "errors": null }
```

プロバイダを切り替えるにはドライバを差し替えます。

```php
use SchemableValidator\Adapters\Captcha\HCaptchaDriver;
use SchemableValidator\Adapters\Captcha\TurnstileDriver;

// hCaptcha
'captchaDriver' => new HCaptchaDriver('YOUR_SECRET')

// Cloudflare Turnstile
'captchaDriver' => new TurnstileDriver('YOUR_SECRET')
```

テスト・ローカル開発では `NullCaptchaDriver` を使うとネットワーク通信を一切行いません。

```php
use SchemableValidator\Adapters\Captcha\NullCaptchaDriver;

'captchaDriver' => new NullCaptchaDriver() // 常に通る。false を渡すと常に弾く
```

セキュリティ特性、スコア閾値については [バックエンドアダプタ](./backend-adapters.md#captcha-検証ドライバを注入する) を参照してください。

### ベストプラクティス

| 項目 | 説明 |
|:--|:--|
| CSRF トークンの使用 | すべての POST フォームで `createToken()` / `checkToken()` を有効にする |
| CAPTCHA の活用 | 公開フォームには `CaptchaDriver` を注入して `validateCaptcha()` を呼び出し、スパムを防止する |
| 出力のエスケープ | `getResult()` の `value` は `strip_tags` + `htmlspecialchars` 済みだが、HTML に出力する際は改めてエスケープする |

---

## セッション管理

`FormController` 機能により、検証済みデータをセッションに保存し、入力→確認→完了のようなマルチページフォームをまたいで状態を保持します。

::: code-group

```php [Core]
use SchemableValidator\Infrastructure\FormController;

$form = new FormController();
```

```php [WordPress]
$form = schv_form();
```

:::

| メソッド | 説明 |
|:--|:--|
| `save(array $data): void` | `getResult()` の返り値をセッションに保存 |
| `get(): ?array` | 保存済みデータを取得。未保存なら `null` |
| `clear(): void` | セッションからデータを削除 |

::: code-group

```php [Core]
use SchemableValidator\Infrastructure\FormController;

// Step 1: 検証 → 保存 → リダイレクト
$result = $validator->validate($_POST)->getResult();
$all_valid = array_reduce($result, fn($c, $i) => $c && $i['is_valid'], true);

if ($all_valid) {
  (new FormController())->save($result);
  header('Location: /confirm/');
  exit;
}

// Step 2: 確認画面で取得
$data = (new FormController())->get();

// Step 3: 完了後にクリア
(new FormController())->clear();
```

```php [WordPress]
// Step 1: 検証 → 保存 → リダイレクト
$result = $validator->validate($_POST)->getResult();
$all_valid = array_reduce($result, fn($c, $i) => $c && $i['is_valid'], true);

if ($all_valid) {
  schv_form()->save($result);
  wp_redirect('/confirm/');
  exit;
}

// Step 2: 確認画面で取得
$data = schv_form()->get();

// Step 3: 完了後にクリア
schv_form()->clear();
```

:::

::: warning セッションアフィニティが必要です
`FormController` は PHP のネイティブセッション（`$_SESSION`）にデータを保存します。
ロードバランサー下で sticky session が保証されない環境では、ステップ間でリクエストが別のサーバーに振られ、確認画面で `get()` が `null` を返す場合があります。

たとえば、セッションアフィニティのない 2 台構成では次のような状態になります。

```
ユーザー → サーバー A（Step 1: save() が A のセッションファイルに書き込む）
ユーザー → サーバー B（Step 2: get() が B のセッションファイルを読む → null）
```

回避するには、共有セッションバックエンドを設定してください。

```php
// php.ini または実行時設定
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis-host:6379');
```

あるいは、`FormController` を使わず、暗号化したフォームデータを hidden field で引き回すトークン方式に置き換える方法もあります。この方式ではサーバー側のセッション状態に依存しません。
:::

---

## Template

プレースホルダー付きテンプレート文字列に検証済みデータを差し込みます。メール本文の生成などに使います。

テンプレートファイルの例 (`templates/user.txt`, `templates/admin.txt`):

```
お問い合わせを受け付けました。

お名前: {name}
メールアドレス: {email}

ご連絡内容:
{body}

折り返しご連絡いたします。
```

```
新しいお問い合わせが届きました。

氏名: {name}
返信先: {email}

内容:
{body}
```

テンプレートファイルをインクルードしてインスタンス化する:

::: code-group

```php [Core]
use SchemableValidator\Orchestration\Template;

$template = new Template([
  'aliases'   => [
    'name'  => 'name',   // テンプレートの {name} → $data['name']['value']
    'email' => 'email',
    'body'  => 'body',
  ],
  'templates' => [
    'user'  => file_get_contents(__DIR__ . '/templates/user.txt'),
    'admin' => file_get_contents(__DIR__ . '/templates/admin.txt'),
  ],
]);
```

```php [WordPress]
$template = schv_template([
  'aliases'   => ['name' => 'name', 'email' => 'email', 'body' => 'body'],
  'templates' => [
    'user'  => 'SCHV_REPLY_FORMAT_FOR_user',   // WP オプション名
    'admin' => 'SCHV_REPLY_FORMAT_FOR_admin',
  ],
]);
```

:::

```php
$user_mail  = $template->get('user');
$admin_mail = $template->get('admin');
$all        = $template->getAll();
```

::: info
`aliases` は `テンプレート内キー => $data のフィールド名` のマッピングです。フォームフィールド名とテンプレートプレースホルダー名が異なる場合に使います。
:::

::: warning WordPress
`templates` の値は WP オプション名として解釈され、`get_option()` で本文を取得します。テンプレート文字列を直接渡さないでください。
:::

