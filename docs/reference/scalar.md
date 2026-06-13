# SV::boolean() / SV::enum() — 真偽値・選択肢型

---

## SV::boolean()

真偽値を検証する。`true` / `false` のほか、フォーム入力として `"1"` / `"0"` / `"on"` / `"off"` / `"yes"` / `"no"` も受け付ける。

```php
SV::boolean()
```

**JSON Schema 出力:**
```json
{ "type": "boolean" }
```

**用途:** 利用規約への同意チェックボックス、フラグ入力。

```php
$schema = SV::object([
  'agreement' => SV::boolean(),
  'newsletter' => SV::boolean()->optional(),
]);
```

```json
{
  "properties": {
    "agreement":  { "type": "boolean" },
    "newsletter": { "type": "boolean" }
  },
  "required": ["agreement"]
}
```

> HTML の `<input type="checkbox">` は未チェック時に値が送信されないため、サーバー側では `$_POST['agreement'] ?? ''` のように扱うこと。

---

## SV::enum(values)

定義した選択肢のいずれかであることを検証する。

```php
SV::enum(array $values)
```

| パラメータ | 型 | 説明 |
|:--|:--|:--|
| `$values` | `string[]` | 許容する文字列の配列 |

**JSON Schema 出力:**
```json
{ "type": "string", "enum": ["a", "b", "c"] }
```

**用途:** `<select>` や radio ボタンの選択肢。DB に格納する区分値の検証。

```php
// お問い合わせ種別
SV::enum(['general', 'support', 'sales', 'other'])

// 任意のステータス選択
SV::enum(['draft', 'published', 'archived'])->optional()
```

```json
{ "type": "string", "enum": ["general", "support", "sales", "other"] }
```

### 注意: 空文字の扱い

HTML の `<select>` で「選択してください」を `value=""` として配置している場合、`optional()` を付けると空文字がバリデーションをスキップする。必須にする場合は `optional()` を付けないこと。

```php
// 空文字は不可（選択必須）
SV::enum(['general', 'support', 'other'])

// 未選択（空文字）を許容する
SV::enum(['general', 'support', 'other'])->optional()
```

---

## 組み合わせ例

```php
$schema = SV::object([
  'type'      => SV::enum(['question', 'feedback', 'bug']),
  'priority'  => SV::enum(['low', 'medium', 'high'])->optional(),
  'published' => SV::boolean(),
]);
```
