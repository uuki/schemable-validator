# 修飾子 — optional / nullable

修飾子はすべてのフィールド型（`string` / `integer` / `number` / `boolean` / `enum` / `file` / `respect`）に付与できる。

---

## .optional() {#optional}

フィールドを**任意入力**にする。JSON Schema の `required` 配列から除外される。

```php
SV::string()->optional()
```

**効果:**
- `required` 配列に含まれなくなる
- SDK の `validateObject` は空文字のとき制約チェックをスキップする
- Zod 統合では `z.preprocess(v => v === '' ? undefined : v, zType.optional())` として扱われる

**用途:** 電話番号・会社名・コメントなど、入力しなくてもよいフィールド。

```php
$schema = SV::object([
  'name'  => SV::string()->min(1)->max(100),           // 必須
  'email' => SV::string()->email(),                    // 必須
  'tel'   => SV::string()->pattern('^\d{10,11}$')->optional(),  // 任意
]);
```

```json
{
  "properties": {
    "name":  { "type": "string", "minLength": 1, "maxLength": 100 },
    "email": { "type": "string", "format": "email" },
    "tel":   { "type": "string", "pattern": "^\\d{10,11}$" }
  },
  "required": ["name", "email"]
}
```

> `optional()` は「入力しなくてよい」であり「null を入れてよい」ではない。null を許容するには `.nullable()` を組み合わせる。

---

## .nullable() {#nullable}

フィールドの型に **`null` を追加**する。`null` 値（または空値）を明示的に許容する。

```php
SV::string()->nullable()
```

**効果:**
- JSON Schema の `type` が `["string", "null"]` のような配列になる
- `null` が有効な値として認められる

**用途:** DB の NULL 許容カラム、設定値の未設定状態を明示したい場合。

```php
SV::string()->url()->nullable()
```

```json
{ "type": ["string", "null"], "format": "uri" }
```

---

## optional と nullable の違い

| | `optional()` | `nullable()` |
|:--|:--|:--|
| 意味 | 入力しなくてよい（省略可） | `null` を値として送れる |
| `required` への影響 | `required` から除外 | 影響しない |
| 空文字の扱い | SDK がスキップ | `null` に変換されうる |
| JSON Schema | `required` から除外 | `type: ["...", "null"]` |

### 両方付ける場合

「任意入力かつ null 許容」にする場合は両方を付ける。

```php
SV::string()->url()->nullable()->optional()
```

```json
{
  "type": ["string", "null"],
  "format": "uri"
}
```

この場合 `required` にも含まれず、`null` 値も受け付ける。

---

## 組み合わせ例

```php
$schema = SV::object([
  'username' => SV::string()->min(3)->max(20),           // 必須、null 不可
  'bio'      => SV::string()->max(500)->optional(),      // 任意、null 不可
  'website'  => SV::string()->url()->nullable()->optional(), // 任意、null 可
  'age'      => SV::integer()->min(0)->max(150)->optional(), // 任意数値
]);
```
