# SV::array() — 配列型

複数選択・繰り返し入力フィールドに使う型。各要素を指定したスキーマで検証します。

```php
SV::array(AbstractFieldSchema $items): ArraySchema
```

| パラメータ | 型 | 説明 |
|:--|:--|:--|
| `$items` | `AbstractFieldSchema` | 各要素のスキーマ定義 |

**JSON Schema 出力:**

```json
{ "type": "array", "items": { ... } }
```

---

## 基本的な使い方

```php
use SchemableValidator\SV;

// 文字列の配列（各要素は1〜100文字）
SV::array(SV::string()->min(1)->max(100))

// enum 値の配列（チェックボックス複数選択）
SV::array(SV::enum(['php', 'js', 'python', 'ruby']))

// integer の配列
SV::array(SV::integer()->min(1))
```

---

## .minItems(n) {#minitems}

配列の**最小要素数**を設定します。

```php
SV::array($items)->minItems(int $n)
```

**JSON Schema キーワード:** `minItems`

```php
// 最低1つは選択必須
SV::array(SV::enum(['a', 'b', 'c']))->minItems(1)
```

```json
{ "type": "array", "items": { "type": "string", "enum": ["a","b","c"] }, "minItems": 1 }
```

---

## .maxItems(n) {#maxitems}

配列の**最大要素数**を設定します。

```php
SV::array($items)->maxItems(int $n)
```

**JSON Schema キーワード:** `maxItems`

```php
// 最大3つまで選択
SV::array(SV::enum(['a', 'b', 'c', 'd']))->maxItems(3)
```

---

## 実装例

### PHP（スキーマ定義）

```php
$schema = SV::object([
  'name'     => SV::string()->min(1)->max(100),
  'tags'     => SV::array(SV::string()->min(1)->max(50))->minItems(1)->maxItems(5)->optional(),
  'interests'=> SV::array(SV::enum(['sports', 'music', 'travel', 'food']))->optional(),
]);
```

### JSON Schema 出力

```json
{
  "type": "object",
  "properties": {
    "name":      { "type": "string", "minLength": 1, "maxLength": 100 },
    "tags":      { "type": "array", "items": { "type": "string", "minLength": 1, "maxLength": 50 }, "minItems": 1, "maxItems": 5 },
    "interests": { "type": "array", "items": { "type": "string", "enum": ["sports","music","travel","food"] } }
  },
  "required": ["name"]
}
```

### サーバー側検証

配列フィールドは `$_POST` で `tags[]` として送られます。

```php
$result = $schema->toValidator()->validate($_POST)->getResult();
```

### クライアント側（`@schemable-validator/client`）

`validateObject` に `Record<string, string | string[]>` を渡す。

```typescript
import { validateObject } from '@schemable-validator/client'

const result = validateObject(
  { name: 'Alice', tags: ['php', 'js'] },
  schema,
)
```

配列フィールドの `FieldResult.value` は `string[]` になります。

```typescript
result.tags
// { value: ['php', 'js'], is_valid: true, errors: null }
```
