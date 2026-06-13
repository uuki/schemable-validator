# SV::object() — オブジェクト定義と出力

---

## SV::object(fields) {#object}

フィールドの集合からスキーマを定義する。すべてのフィールド定義はここに集約される。

```php
SV::object(array<string, AbstractFieldSchema> $fields): SchemaBuilder
```

| パラメータ | 型 | 説明 |
|:--|:--|:--|
| `$fields` | `array<string, AbstractFieldSchema>` | フィールド名 → フィールドスキーマの連想配列 |

**用途:** 1つのフォームや API リクエストに対してスキーマを一元定義する起点。

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name'  => SV::string()->min(1)->max(100),
  'email' => SV::string()->email(),
  'age'   => SV::integer()->min(0)->optional(),
  'type'  => SV::enum(['general', 'support']),
]);
```

---

## .toJsonSchema() {#tojsonschema}

スキーマを **JSON Schema draft 2020-12 の配列**として返す。

```php
$schema->toJsonSchema(): array
```

- `SV::file()` / `SV::respect()` フィールドは `properties` から除外され、`x-unmapped-fields` に記録される
- `optional()` が付いていないフィールドは `required` 配列に含まれる

**用途:** PHP 側でスキーマを配列として操作したい場合、REST レスポンスの手動加工。

```php
$array = $schema->toJsonSchema();
// [
//   '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
//   'type'       => 'object',
//   'properties' => [...],
//   'required'   => [...],
// ]
```

---

## .toJson() {#tojson}

スキーマを **JSON 文字列**として返す。`JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` で出力される。

```php
$schema->toJson(): string
```

**用途:** REST エンドポイントのレスポンスボディ、デバッグ表示。

```php
echo $schema->toJson();
```

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "properties": {
    "name":  { "type": "string", "minLength": 1, "maxLength": 100 },
    "email": { "type": "string", "format": "email" },
    "age":   { "type": "integer", "minimum": 0 },
    "type":  { "type": "string", "enum": ["general", "support"] }
  },
  "required": ["name", "email", "type"]
}
```

---

## .toValidator() {#tovalidator}

スキーマから Respect/Validation ベースの **`Validator` インスタンス**を生成する。  
`SV::file()` / `SV::respect()` を含むすべてのフィールドを検証できる。

```php
$schema->toValidator(array $options = []): Validator
```

| パラメータ | 型 | 説明 |
|:--|:--|:--|
| `$options` | `array` | `Validator` のオプション（reCAPTCHA 設定など） |

**用途:** サーバー側でのフォーム検証。`toJsonSchema()` と組み合わせることで、定義の二重管理を避ける。

```php
// テキスト検証
$result = $schema->toValidator()->validate($_POST)->getResult();

// ファイルを含む検証
$result = $schema->toValidator()
  ->validate($_POST)
  ->validateFiles($_FILES)
  ->getResult();

// reCAPTCHA を含む検証
$result = $schema->toValidator(['recaptcha_secret' => 'SECRET'])
  ->validate($_POST)
  ->validateReCaptcha()
  ->getResult();
```

### getResult() の戻り値

```json
{
  "name":  { "value": "Alice", "is_valid": true,  "errors": null },
  "email": { "value": "bad",   "is_valid": false, "errors": "\"bad\" must be a valid email address" }
}
```

---

## WordPress REST エンドポイントへの登録

```php
// GET /wp-json/schv/v1/schema/contact → JSON Schema を返す
schv_register_schema('/schema/contact', $schema);
```

```php
// URL の取得
$url = schv_schema_url('/schema/contact');
// → https://example.com/wp-json/schv/v1/schema/contact
```

ETag と `Cache-Control: public, max-age=3600` が自動で付与される。

---

## 単一スキーマで全体をまかなう例

```php
$schema = SV::object([
  'name'   => SV::string()->min(1)->max(100),
  'email'  => SV::string()->email(),
  'tel'    => SV::string()->pattern('^0\d{9,10}$')->optional(),
  'type'   => SV::enum(['general', 'support', 'other']),
  'body'   => SV::string()->min(10)->max(1000),
  'avatar' => SV::file(['image/jpeg', 'image/png'])->optional(),
]);

// 1. REST で公開
schv_register_schema('/schema/contact', $schema);

// 2. サーバー側検証
add_action('template_redirect', function () use ($schema) {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

  $result = $schema->toValidator()
    ->validate($_POST)
    ->validateFiles($_FILES)
    ->getResult();
});
```
