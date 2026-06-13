# SV::object() — オブジェクト定義と出力

---

## SV::object(fields) {#object}

フィールドの集合からスキーマを定義します。すべてのフィールド定義はここに集約されます。

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

- `SV::file()` / `SV::respect()` フィールドは `properties` から除外され、`x-unmapped-fields` に記録されます
- `optional()` が付いていないフィールドは `required` 配列に含まれます

**用途:** PHP 側でスキーマを配列として操作したい場合や、REST レスポンスを手動加工する場合。

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

スキーマを **JSON 文字列**として返します。`JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` で出力されます。

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

## .when(field, expr, require) {#when}

あるフィールドが条件を満たすとき、別フィールドを**条件付きで必須**にする。

```php
$schema->when(string $field, WhenExpr|scalar $expr, array $require): self
```

| パラメータ | 型 | 説明 |
|:--|:--|:--|
| `$field` | `string` | 条件を評価するフィールド名 |
| `$expr` | `WhenExpr` \| `scalar` | 比較式。スカラーを渡した場合は `SV::equal($value)` と等価 |
| `$require` | `string[]` | 条件を満たすとき必須にするフィールド名の配列 |

複数回呼び出せる。  
**用途:** 「種別が "法人" のとき会社名を必須にする」「年齢が 18 未満のとき保護者同意欄を必須にする」など、フィールド間依存のルール。

---

### 比較式一覧 {#when-expressions}

`$expr` には以下の `SV::*` ファクトリを使用します。

| 式 | 比較 | 備考 |
|:--|:--|:--|
| `'value'`（スカラー） | `=== 'value'` | `SV::equal('value')` の省略形 |
| `SV::equal($value)` | `===` | 文字列一致 |
| `SV::notEqual($value)` | `!==` | 文字列不一致 |
| `SV::greaterThanOrEqual($n)` | `>= n` | 数値・以上 |
| `SV::lessThanOrEqual($n)` | `<= n` | 数値・以下 |
| `SV::greaterThan($n)` | `> n` | 数値・より大きい |
| `SV::lessThan($n)` | `< n` | 数値・未満 |
| `SV::field('name')` | — | 別フィールドの値を参照（上記と組み合わせる） |

`SV::equal()` / `SV::notEqual()` の引数に `SV::field('name')` を渡すと、**2フィールド間の比較**になります。  
数値演算子（`>=` / `<=` / `>` / `<`）も同様にフィールド参照を受け取れます。

---

### 使用例

#### スカラー省略形（=== のみ）

```php
SV::object([
  'type'         => SV::enum(['personal', 'company']),
  'company_name' => SV::string()->min(1)->optional(),
])->when('type', 'company', ['company_name']);
```

#### 明示的な === / !==

```php
// type === 'company' のとき company_name を必須
->when('type', SV::equal('company'), ['company_name'])

// role !== 'admin' のとき note を必須
->when('role', SV::notEqual('admin'), ['note'])
```

#### 数値比較

```php
// age >= 18 のとき consent を必須
->when('age', SV::greaterThanOrEqual(18), ['consent'])

// score <= 50 のとき retry を必須
->when('score', SV::lessThanOrEqual(50), ['retry'])

// qty < 1 のとき warn を必須（未満）
->when('qty', SV::lessThan(1), ['warn'])

// level > 10 のとき bonus を必須（より大きい）
->when('level', SV::greaterThan(10), ['bonus'])
```

#### フィールド参照（2フィールド間の比較）

```php
// password === confirm_password のとき hint を必須
->when('password', SV::equal(SV::field('confirm_password')), ['hint'])

// new_password !== old_password のとき change_reason を必須
->when('new_password', SV::notEqual(SV::field('old_password')), ['change_reason'])

// price >= min_price のとき note を必須
->when('price', SV::greaterThanOrEqual(SV::field('min_price')), ['note'])
```

#### 複数条件

```php
SV::object([...])->
  when('plan', SV::equal('enterprise'), ['billing_email'])->
  when('plan', SV::equal('enterprise'), ['contract_name']);
```

---

### JSON Schema 出力 {#when-json-schema}

すべての条件は `x-when` 拡張キーに出力されます。リテラル `===` 条件は標準の `if/then`（単一）または `allOf`（複数）も**併記**されます。

```json
{
  "x-when": [
    { "field": "type",     "op": "===", "equals": "company", "require": ["company_name"] },
    { "field": "age",      "op": ">=",  "equals": 18,        "require": ["consent"] },
    { "field": "password", "op": "===", "equalsField": "confirm_password", "require": ["hint"] }
  ],
  "if":   { "properties": { "type": { "const": "company" } } },
  "then": { "required": ["company_name"] }
}
```

| キー | 内容 |
|:--|:--|
| `field` | 比較元フィールド名 |
| `op` | `===` / `!==` / `>=` / `<=` / `>` / `<` |
| `equals` | リテラル値（`equalsField` がない場合） |
| `equalsField` | 比較先フィールド名（`SV::field()` を使った場合） |
| `require` | 条件成立時に必須とするフィールド名の配列 |

> `@schemable-validator/client` の `validateObject` は `x-when` を優先して評価します。`x-when` がない場合は標準の `if/then` / `allOf` にフォールバックします。

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

ETag と `Cache-Control: public, max-age=3600` が自動で付与されます。

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
