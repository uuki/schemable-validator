# 機能ガイド

## Validator

フィールドスキーマに対して入力値を検証する中核クラスです。テキスト・ファイル・reCAPTCHA の検証をメソッドチェーンで組み合わせられます。

### 1. インスタンス化

::: code-group

```php [Core]
use SchemableValidator\Validator;

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

注意: `creditCard` および `postalCode` ルールは **@deprecated** であり、`Drivers\Respect\RespectRules` に移動されました。
:::

### メソッドチェーン

```php
$result = $validator
  ->validate($_POST)
  ->validateFiles($_FILES)
  ->validateReCaptcha()
  ->getResult();
```

---

## SchemaBuilder

`SV::object()` を起点とする宣言的なスキーマ定義 API です。同一の定義から **JSON Schema (draft 2020-12) への出力** と **Validator インスタンスの生成** の両方を行えます。Validator を直接定義する場合と異なり、クライアントサイドのバリデーションライブラリや OpenAPI ツールとの連携が可能になります。

### フィールド定義

`SV::object()` にフィールド名とフィールドスキーマの連想配列を渡して定義します。

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name'    => SV::string()->min(2)->max(50),
  'email'   => SV::string()->email(),
  'tel'     => SV::string()->pattern('^0\d{9,10}$')->optional(),
  'type'    => SV::enum(['general', 'support', 'other']),
  'age'     => SV::integer()->min(0)->max(150)->optional(),
  'website' => SV::string()->url()->nullable()->optional(),
  'avatar'  => SV::file(['image/jpeg', 'image/png'])->optional(),
]);
```

主なフィールド型:

| メソッド | JSON Schema `type` | 主な制約メソッド |
|:--|:--|:--|
| `SV::string()` | `"string"` | `.min()` `.max()` `.email()` `.url()` `.pattern()` |
| `SV::integer()` | `"integer"` | `.min()` `.max()` |
| `SV::number()` | `"number"` | `.min()` `.max()` |
| `SV::boolean()` | `"boolean"` | - |
| `SV::enum(['a', 'b'])` | `"string"` + `enum` | - |
| `SV::file(['image/jpeg'])` | - ※JSON Schema 変換不可 | - |
| `SV::respect(v::...)` | - ※JSON Schema 変換不可 | - |

修飾子:

| 修飾子 | 効果 |
|:--|:--|
| `.optional()` | `required` 配列から除外 |
| `.nullable()` | `null` を許容する型に拡張 |

### JSON Schema への出力

```php
echo $schema->toJson();          // JSON 文字列
$array = $schema->toJsonSchema(); // 配列
```

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "properties": {
    "name":  { "type": "string", "minLength": 2, "maxLength": 50 },
    "email": { "type": "string", "format": "email" },
    "type":  { "type": "string", "enum": ["general", "support", "other"] }
  },
  "required": ["name", "email", "type"]
}
```

::: info フロントエンドとのルール共有
フロントエンドとバリデーションルールを共有したい場合は SchemaBuilder を使います。`toJson()` で出力した JSON Schema を REST API 経由でフロントエンドに渡せば、PHP 側のルール定義を唯一の情報源として管理できます。重複定義や実装の乖離を防げます。
:::

### Validator への変換

`toValidator()` で `Validator` インスタンスを生成します。`validateFiles()` や `validateReCaptcha()` をそのままチェーンできます。

```php
$result = $schema->toValidator()
  ->validate($_POST)
  ->validateFiles($_FILES)
  ->getResult();
```

::: tip
条件付き必須（`.when()`）・WordPress REST エンドポイント登録（`schv_register_schema()`）など詳細は [SchemaBuilder](/ja/schema-builder) を参照してください。
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

`MessageDict` を使うと、フィールド×ルール単位でエラーメッセージを辞書的に定義できます。日本語プリセットや WordPress フィルターによるサイト全体への適用もサポートしています。

#### Step 1: 辞書ファイルの用意

`MessageDict` には **ロケールデフォルト** と **フィールド定義** の2種類を渡せます。それぞれ PHP ファイルとして用意しておくと管理しやすくなります。

**ロケールデフォルト** - ルール ID をキーとし、フィールドを問わず共通で使うメッセージを定義します。

```php
// messages/ja.php — エンジン中立のボキャブラリキーを使用（I18n/DefaultMessages.php 参照）
return [
  'string'    => '文字列で入力してください',
  'minLength' => '最低{min}文字で入力してください',
  'maxLength' => '最大{max}文字まで入力できます',
  'email'     => '有効なメールアドレスを入力してください',
  'required'  => '必須項目です',
  'integer'   => '整数で入力してください',
  'number'    => '数値で入力してください',
  'uri'       => '有効なURLを入力してください',
  'pattern'   => '入力形式が正しくありません',
  'enum'      => '選択肢から選んでください',
];
```

**フィールド定義** - フィールド名をキーとし、フィールド固有のメッセージを上書きします。3つの書き方があります。

```php
// messages/fields.php
return [
  // パターン1: フィールド全体（どのルールが失敗しても同じメッセージ）
  'email' => 'メールアドレスを正しく入力してください',

  // パターン2: フィールド×ルール固有（ルールごとに個別指定）
  'name' => [
    'length' => '名前は2〜50文字で入力してください',
  ],

  // パターン3: 複数ルールをまとめて個別指定
  'body' => [
    'required' => '本文を入力してください',
    'pattern'  => '本文は10文字以上で入力してください',
  ],
];
```

::: info 優先順位
同一フィールド・ルールに複数の定義が存在する場合、以下の順で解決されます。

1. フィールド×ルール固有 - `['name' => ['length' => '...']]`
2. フィールド全体 - `['email' => '...']`
3. ロケールデフォルト - `messages/ja.php` の内容
4. DefaultMessages カタログ（エンジン中立）
:::

#### Step 2: 読み込み

用意したファイルを `MessageDict` に渡してインスタンスを生成します。

```php
use SchemableValidator\I18n\MessageDict;

// 組み込みの日本語プリセットをそのまま使う
$dict = MessageDict::ja();

// カスタム辞書ファイルを使う
$dict = new MessageDict(
  require __DIR__ . '/messages/fields.php', // フィールド定義
  require __DIR__ . '/messages/ja.php'       // ロケールデフォルト
);

// 日本語プリセット + フィールド定義を組み合わせる
$dict = MessageDict::ja(
  require __DIR__ . '/messages/fields.php'
);
```

`MessageDict::en()` は DefaultMessages カタログ（エンジン中立）のメッセージをそのまま返します。

#### Step 3: Validator への渡し方

::: code-group

```php [Core]
use SchemableValidator\Validator;
use SchemableValidator\I18n\MessageDict;

$dict = MessageDict::ja(require __DIR__ . '/messages/fields.php');

$validator = new Validator($schema, [], [], $dict);
```

```php [WordPress]
use SchemableValidator\I18n\MessageDict;

$dict = MessageDict::ja(require __DIR__ . '/messages/fields.php');

$validator = schv_validator($schema, [], $dict);
```

:::

SchemaBuilder 経由で渡す場合:

```php
use SchemableValidator\SV;
use SchemableValidator\I18n\MessageDict;

$dict = MessageDict::ja(require __DIR__ . '/messages/fields.php');

$result = SV::object([
  'name'  => SV::string()->min(2)->max(50),
  'email' => SV::string()->email(),
])->withMessages($dict)
  ->toValidator()
  ->validate($_POST)
  ->getResult();
```

#### サイト全体のデフォルト（WordPress）

`schv_message_dict` フィルターで辞書を上書きすると、`schv_validator()` 呼び出し時に自動で適用されます。

```php
add_filter('schv_message_dict', function (MessageDict $dict): MessageDict {
  return $dict->merge(require __DIR__ . '/messages/fields.php');
});

// $dict を省略すると schv_message_dict フィルターの結果が自動適用される
$validator = schv_validator($schema);
```

---

## セキュリティ

フォームのセキュリティに関わる機能とベストプラクティスを説明します。

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

### ベストプラクティス

| 項目 | 説明 |
|:--|:--|
| CSRF トークンの使用 | すべての POST フォームで `createToken()` / `checkToken()` を有効にする |
| reCAPTCHA の活用 | 公開フォームには `validateReCaptcha()` を組み合わせてスパムや自動送信を防止する |
| 出力のエスケープ | `getResult()` の `value` は `strip_tags` + `htmlspecialchars` 済みだが、HTML に出力する際は改めてエスケープする |

---

## セッション管理

`FormController` 機能により、検証済みデータをセッションに保存し、入力→確認→完了のようなマルチページフォームをまたいで状態を保持します。

::: code-group

```php [Core]
use SchemableValidator\Controllers\FormController;

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
use SchemableValidator\Controllers\FormController;

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
use SchemableValidator\Template;

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

---

## その他機能

### reCAPTCHA v3

フロントエンドから `$_POST['recaptcha_token']` を送信してください。

::: code-group

```php [Core]
$validator = new Validator($schema, [
  'recaptcha_secret'      => 'YOUR_SECRET_KEY',
  'recaptcha_valid_score' => 0.5,
]);
```

```php [WordPress]
$validator = schv_validator($schema, [
  'recaptcha_secret'      => 'YOUR_SECRET_KEY',
  'recaptcha_valid_score' => 0.5,
]);
```

:::

```php
$result = $validator
  ->validate($_POST)
  ->validateReCaptcha([
    'action' => 'contact', // オプション: action 名の一致も検証
  ])
  ->getResult();
```
