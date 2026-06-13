# Feature Guide

## Validator

### スキーマ定義

[Respect/Validation](https://respect-validation.readthedocs.io/en/latest/validators/) のルールをフィールド名にマップします。

```php
use Respect\Validation\Validator as v;

$schema = [
  'name'  => v::stringType()->length(2, 50),
  'email' => v::email(),
  'tel'   => v::regex('/^(0\d{9,10}|0\d{1,4}-\d{1,4}-\d{3,4})$/'),
  'type'  => v::in(['general', 'support', 'sales', 'other']),
  'body'  => v::regex('/^.{10,}$/su'),
];
```

複数ルールはメソッドチェーンで結合できます。

```php
v::stringType()->notEmpty()->length(1, 100)
```

### インスタンス化

```php
use SchemableValidator\Validator;

$validator = new Validator($schema);

// WordPress ヘルパー関数
$validator = schv_validator($schema);
```

オプション（reCAPTCHA 設定）を渡す場合:

```php
$validator = new Validator($schema, [
  'recaptcha_secret'      => 'YOUR_SECRET_KEY',
  'recaptcha_valid_score' => 0.5,
]);
```

### テキストフィールドの検証

```php
$result = $validator->validate($_POST)->getResult();
```

入力値は自動的にサニタイズ（`strip_tags` + `htmlspecialchars`）されます。

### ファイルの検証

```php
// $_FILES をそのまま渡す場合
$result = $validator->validateFiles($_FILES)->getResult();

// $_FILES 以外の配列を渡す場合
$result = $validator->validateFiles($data, ['native_files' => false])->getResult();
```

`FileExtension` カスタムルールで MIME タイプを検証できます:

```php
use SchemableValidator\Rules\FileExtension;

$schema = [
  'file' => new FileExtension(['image/jpeg', 'image/png']),
];
```

### reCAPTCHA v3

```php
// フロントエンドから $_POST['recaptcha_token'] を送信する

$result = $validator
  ->validate($_POST)
  ->validateReCaptcha([
    'action' => 'contact', // オプション: action 名の一致も検証
  ])
  ->getResult();
```

### メソッドチェーン

```php
$result = $validator
  ->validate($_POST)
  ->validateFiles($_FILES)
  ->validateReCaptcha()
  ->getResult();
```

### 結果の構造

`getResult()` は以下の形式の連想配列を返します。

```php
[
  'name' => [
    'value'    => 'Alice',   // 入力値（サニタイズ済み）
    'is_valid' => true,
    'errors'   => null,
  ],
  'email' => [
    'value'    => 'bad',
    'is_valid' => false,
    'errors'   => '"bad" must be a valid email address',
  ],
]
```

---

## CSRF トークン

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

---

## FormController（マルチページフォーム）

検証済みデータをセッションに保存し、ページをまたいで引き回す。

```php
use SchemableValidator\Controllers\FormController;

$form = new FormController();

// WordPress ヘルパー関数
$form = schv_form();
```

| メソッド | 説明 |
|:--|:--|
| `save(array $data): void` | `getResult()` の返り値をセッションに保存 |
| `get(): ?array` | 保存済みデータを取得。未保存なら `null` |
| `clear(): void` | セッションからデータを削除 |

```php
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

---

## Template（メール本文の組み立て）

セッションに保存された検証済みデータを、定義済みテンプレートに差し込みます。

### 基本

```php
use SchemableValidator\Template;

$template = new Template([
  'aliases'   => [
    'name'  => 'name',   // テンプレートの {name} → $data['name']['value']
    'email' => 'email',
    'body'  => 'body',
  ],
  'templates' => [
    'user'  => "Dear {name},\nThank you for your message.\n\n{body}",
    'admin' => "From: {name} <{email}>\n\n{body}",
  ],
]);

$user_mail  = $template->get('user');
$admin_mail = $template->get('admin');
$all        = $template->getAll();
```

`aliases` は `テンプレート内キー => $data のフィールド名` のマッピングです。
フォームフィールド名とテンプレートプレースホルダー名が異なる場合に使います。

### WordPress ヘルパー

```php
$template = schv_template([
  'aliases'   => ['name' => 'name', 'email' => 'email', 'body' => 'body'],
  'templates' => [
    'user'  => 'SCHV_REPLY_FORMAT_FOR_user',   // WP オプション名
    'admin' => 'SCHV_REPLY_FORMAT_FOR_admin',
  ],
]);

$user_mail = $template->get('user');
```

WordPress 環境では `templates` の値を WP オプション名として解釈し、
`get_option()` で本文を取得します。テンプレート文字列を直接渡さないでください。
