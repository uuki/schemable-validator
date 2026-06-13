# SchemaBuilder — JSON 出力サンプル

`SV::object()` で定義したスキーマは `toJson()` / `toJsonSchema()` で JSON Schema (draft 2020-12) に変換できます。

---

## フィールド型一覧

| メソッド | JSON Schema `type` | 備考 |
|:--|:--|:--|
| `SV::string()` | `"string"` | `.email()` `.url()` `.min()` `.max()` `.pattern()` |
| `SV::integer()` | `"integer"` | `.min()` `.max()` |
| `SV::number()` | `"number"` | `.min()` `.max()` (int/float) |
| `SV::boolean()` | `"boolean"` | |
| `SV::enum(['a','b'])` | `"string"` + `enum` | |
| `SV::file(['image/jpeg'])` | — | JSON Schema 変換不可。`x-unmapped-fields` に記録される |
| `SV::respect(v::...)` | — | JSON Schema 変換不可。`x-unmapped-fields` に記録される |

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

JSON Schema に変換できないフィールド（ファイルアップロード・カスタム Respect ルール）は
`x-unmapped-fields` 拡張キーに名前だけ記録されます。
バリデーション自体は `toValidator()` を通じて Respect/Validation で行われます。

```php
// JSON Schema として渡す場合
$jsonSchema = $schema->toJsonSchema(); // array
$json       = $schema->toJson();       // string

// Respect バリデーターとして使う場合（ファイルフィールドも含む）
$validator = $schema->toValidator();
$result    = $validator->validate($_POST)->validateFiles($_FILES)->getResult();
```

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
  "name":  { "value": "",             "errors": "- \"\" must have a length between 1 and 100", "is_valid": false },
  "email": { "value": "not-an-email", "errors": "- \"not-an-email\" must be valid email",       "is_valid": false }
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
