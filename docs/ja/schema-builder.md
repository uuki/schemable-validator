# SchemaBuilder

`SchemaBuilder` は Schemable Validator の中心となるクラスです。バリデーションルールを PHP で一度定義するだけで、以下の2通りの用途に同時に使えます。

- **サーバーサイド** — `toValidator()` で `Validator` に変換して検証（デフォルトは NativeAdapter、依存なし）。
- **クライアントサイド** — `toJson()` / `toJsonSchema()` で標準 JSON Schema (draft 2020-12) にエクスポートし、Zod・Valibot・AJV などの JS バリデーターで利用。

| 機能 | 説明 |
|---|---|
| フルエント API | `SV::string()->email()->min(3)->max(100)` |
| JSON Schema エクスポート | `toJson()` / `toJsonSchema()` — draft 2020-12 準拠 |
| サーバーバリデーション | `toValidator()->validate($data)->getResult()` |
| 条件付き必須 | `->when('type', SV::equal('company'), ['company_name'])` |
| WordPress REST | `schv_register_schema('/contact', $schema)` — スキーマを GET エンドポイントとして公開 |
| 変換不可フィールド | `SV::file()` / `SV::respect()` は `x-unmapped-fields` に記録され、サーバーサイドのみ検証 |

## 基本的な使い方

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name'  => SV::string()->min(1)->max(100),
  'email' => SV::string()->email(),
  'tel'   => SV::string()->pattern('^0\d{9,10}$')->optional(),
]);

// サーバーサイド検証
$result = $schema->toValidator()->validate($_POST)->getResult();

// JS クライアント向けにエクスポート
header('Content-Type: application/json');
echo $schema->toJson();
```

---

## フィールド型一覧

| メソッド | JSON Schema `type` | 備考 |
|:--|:--|:--|
| `SV::string()` | `"string"` | `.email()` `.url()` `.min()` `.max()` `.pattern()` |
| `SV::integer()` | `"integer"` | `.min()` `.max()` |
| `SV::number()` | `"number"` | `.min()` `.max()` (int/float) |
| `SV::boolean()` | `"boolean"` | |
| `SV::enum(['a','b'])` | `"string"` + `enum` | |
| `SV::file(['image/jpeg'])` | - | JSON Schema 変換不可。`x-unmapped-fields` に記録される |
| `SV::respect(v::...)` | - | **@deprecated** — 代わりに `SV::custom()` または `RespectRules::rule()` を使用。JSON Schema 変換不可。`x-unmapped-fields` に記録される |
| `SV::custom(callable, message)` | - | 依存なしのエスケープハッチ。`CustomFieldSchema` を返す。`x-unmapped-fields` に記録される |

修飾子:

| 修飾子 | 効果 |
|:--|:--|
| `.optional()` | `required` 配列から除外 |
| `.nullable()` | `"type"` を `["string", "null"]` のように配列化 |

---

## サンプル 1: お問い合わせフォーム

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name'  => SV::string()->min(1)->max(100),
  'email' => SV::string()->email(),
  'tel'   => SV::string()->pattern('^(0\d{9,10}|0\d{1,4}-\d{1,4}-\d{3,4})$')->optional(),
  'type'  => SV::enum(['general', 'support', 'sales', 'other']),
  'body'  => SV::string()->min(10),
]);

echo $schema->toJson();
```

出力:

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "properties": {
    "name": {
      "type": "string",
      "minLength": 1,
      "maxLength": 100
    },
    "email": {
      "type": "string",
      "format": "email"
    },
    "tel": {
      "type": "string",
      "pattern": "^(0\\d{9,10}|0\\d{1,4}-\\d{1,4}-\\d{3,4})$"
    },
    "type": {
      "type": "string",
      "enum": ["general", "support", "sales", "other"]
    },
    "body": {
      "type": "string",
      "minLength": 10
    }
  },
  "required": ["name", "email", "type", "body"]
}
```

`tel` は `.optional()` のため `required` に含まれません。

---

## サンプル 2: ユーザープロフィール (nullable / file)

```php
$schema = SV::object([
  'username' => SV::string()->min(3)->max(20)->pattern('^[a-zA-Z0-9_]+$'),
  'age'      => SV::integer()->min(0)->max(150)->optional(),
  'score'    => SV::number()->min(0.0)->max(5.0)->optional(),
  'active'   => SV::boolean(),
  'website'  => SV::string()->url()->nullable()->optional(),
  'avatar'   => SV::file(['image/jpeg', 'image/png', 'image/webp'])->optional(),
]);
```

出力:

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "properties": {
    "username": {
      "type": "string",
      "minLength": 3,
      "maxLength": 20,
      "pattern": "^[a-zA-Z0-9_]+$"
    },
    "age": {
      "type": "integer",
      "minimum": 0,
      "maximum": 150
    },
    "score": {
      "type": "number",
      "minimum": 0,
      "maximum": 5
    },
    "active": {
      "type": "boolean"
    },
    "website": {
      "type": ["string", "null"],
      "format": "uri"
    }
  },
  "required": ["username", "active"],
  "x-unmapped-fields": ["avatar"]
}
```

- `website` は `.nullable()` により `"type": ["string", "null"]`
- `avatar` は `SV::file()` (JSON Schema に対応するキーワードがない) のため `properties` から除外され `x-unmapped-fields` に記録されます

---

## `x-unmapped-fields` について

JSON Schema に変換できないフィールド（ファイルアップロード・カスタム callable など）は
`x-unmapped-fields` 拡張キーに名前だけ記録されます。
バリデーション自体は `toValidator()` を通じて BackendAdapter（デフォルトは NativeAdapter）で行われます。

```php
// JSON Schema として渡す場合
$jsonSchema = $schema->toJsonSchema();                       // array
$jsonMeta   = $schema->toJsonSchema(['metaSchema' => true]); // array ($schema URI を含む)
$json       = $schema->toJson();                             // string

// バリデーターとして使う場合（ファイルフィールドも含む）
$validator = $schema->toValidator();
$result    = $validator->validate($_POST)->validateFiles($_FILES)->getResult();
```

### `toValidator()` のパラメータ

```php
$schema->toValidator(
  array $config = []
): Validator
```

| パラメータ | 型 | 説明 |
|:--|:--|:--|
| `$config['adapter']` | `BackendAdapter` | バリデーションエンジン。デフォルト: `NativeAdapter`（依存なし） |
| `$config['fileDriver']` | `FileValidationDriver` | ファイル検証ドライバー。デフォルト: `NativeFileValidator` |
| `$config['imageDriver']` | `ImageDriver` | 画像制約ドライバー。デフォルト: `null`（画像制約をスキップ） |
| `$config['captchaDriver']` | `CaptchaDriver` | CAPTCHA 検証ドライバー。デフォルト: `null`（CAPTCHA 検証は使用不可） |

### `toJsonSchema()` のオプション

```php
$schema->toJsonSchema(array $options = []): array
```

| オプション | 型 | デフォルト | 説明 |
|:--|:--|:--|:--|
| `metaSchema` | `bool` | `false` | `true` の場合、出力に `$schema` URI を含める |

### `toUiSchema()`

JSON Forms / RJSF 互換の UI Schema 配列を返します。

```php
$uiSchema = $schema->toUiSchema(); // array
```

### `customFields()`

`x-custom-fields` 拡張キーでカスタムフィールド名を宣言します。

```php
$schema->customFields(array $names): self
```

### `mergeJsonSchema()`

外部の JSON Schema をビルダーのフィールドとマージします。
外部スキーマが[スキーマエディタ](/ja/feature-guide#スキーマエディタ)等で定義したプリミティブフィールドを供給し、ビルダー側はコードでしか表現できないフィールド（ファイルアップロード、カスタムバリデーション、条件付き必須、ドライバ注入）を供給します。

```php
$schema->mergeJsonSchema(array $jsonSchema): self
```

同名のフィールドが両方に存在する場合、ビルダー側の定義が優先されます。

```php
use SchemableValidator\SV;
use SchemableValidator\Adapters\Captcha\ReCaptchaV3Driver;
use SchemableValidator\Adapters\Native\NativeImageDriver;

// GUI で定義されたスキーマ（StoredSchemaProvider 等から取得）
$gui = [
  'type'       => 'object',
  'properties' => [
    'name'  => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100],
    'email' => ['type' => 'string', 'format' => 'email'],
    'type'  => ['type' => 'string', 'enum' => ['personal', 'company']],
  ],
  'required' => ['name', 'email', 'type'],
];

// GUI では表現できないものをコードで追加
$result = SV::object([
  'avatar' => SV::file(['image/jpeg', 'image/png'], ['maxWidth' => 4096]),
])->mergeJsonSchema($gui)
  ->when('type', SV::equal('company'), ['company_name'])
  ->toValidator([
    'imageDriver'   => new NativeImageDriver(),
    'captchaDriver' => new ReCaptchaV3Driver('SECRET'),
  ])
  ->validate($_POST)
  ->validateFiles($_FILES)
  ->validateCaptcha()
  ->getResult();
```

::: tip WordPress
スキーマエディタで作成したスキーマは `schv_stored_schema($slug)` で読み込めます。
```php
$gui = schv_stored_schema('contact')->toJsonSchema();
$result = SV::object([...])->mergeJsonSchema($gui)->toValidator()->validate($_POST)->getResult();
```
:::

---

## `toValidator()` の出力例

`toValidator()` は `SchemableValidator\Validator` を返す。
`validate()` + `getResult()` で各フィールドの検証結果を取得できる。

```php
$schema    = SV::object(['name' => SV::string()->min(1)->max(100), 'email' => SV::string()->email()]);
$validator = $schema->toValidator();

// 正常系
$result = $validator->validate(['name' => 'Alice', 'email' => 'alice@example.com'])->getResult();
```

```json
{
  "name":  { "value": "Alice",             "errors": null, "is_valid": true },
  "email": { "value": "alice@example.com", "errors": null, "is_valid": true }
}
```

```php
// エラー系
$result = $validator->validate(['name' => '', 'email' => 'not-an-email'])->getResult();
```

```json
{
  "name":  { "value": "",             "errors": "\"\" must have a length between 1 and 100", "is_valid": false },
  "email": { "value": "not-an-email", "errors": "\"not-an-email\" must be valid email",         "is_valid": false }
}
```

---

## WordPress REST エンドポイントへの登録

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name'  => SV::string()->min(1)->max(100),
  'email' => SV::string()->email(),
]);

// GET /wp-json/schv/v1/contact → JSON Schema を返す
schv_register_schema('/contact', $schema);
```
