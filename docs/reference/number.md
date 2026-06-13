# SV::integer() / SV::number() — 数値型

数値入力に使う型。整数のみの場合は `integer`、小数を含む場合は `number` を使う。

---

## SV::integer()

整数値を検証する。HTML フォームからの文字列入力も自動的に整数として評価される。

```php
SV::integer()
```

**JSON Schema 出力:**
```json
{ "type": "integer" }
```

**用途:** 年齢・個数・評価スコア（整数）など。

---

## SV::number()

整数または小数を検証する。

```php
SV::number()
```

**JSON Schema 出力:**
```json
{ "type": "number" }
```

**用途:** 価格・評価スコア（小数）・重量など。

---

## .min(n) {#min}

**最小値**を設定する。

```php
SV::integer()->min(int $n)
SV::number()->min(int|float $n)
```

| パラメータ | 型 | 説明 |
|:--|:--|:--|
| `$n` | `int`（integer）/ `int\|float`（number） | 許容する最小値（以上） |

**JSON Schema キーワード:** `minimum`

**用途:** 0以上の個数、1以上の評価スコア。

```php
// 0以上の年齢
SV::integer()->min(0)->max(150)

// 0.0以上の評価スコア
SV::number()->min(0.0)->max(5.0)
```

```json
{ "type": "integer", "minimum": 0, "maximum": 150 }
```

---

## .max(n) {#max}

**最大値**を設定する。

```php
SV::integer()->max(int $n)
SV::number()->max(int|float $n)
```

| パラメータ | 型 | 説明 |
|:--|:--|:--|
| `$n` | `int`（integer）/ `int\|float`（number） | 許容する最大値（以下） |

**JSON Schema キーワード:** `maximum`

```php
// 1〜100 の整数スコア
SV::integer()->min(1)->max(100)

// 0.5〜5.0 の小数評価
SV::number()->min(0.5)->max(5.0)
```

```json
{ "type": "number", "minimum": 0.5, "maximum": 5.0 }
```

---

## 組み合わせ例

```php
$schema = SV::object([
  // 年齢: 0〜150 の整数、任意入力
  'age'    => SV::integer()->min(0)->max(150)->optional(),

  // 評価: 0.0〜5.0 の数値、任意入力
  'score'  => SV::number()->min(0.0)->max(5.0)->optional(),

  // 数量: 1以上
  'count'  => SV::integer()->min(1),
]);
```

```json
{
  "type": "object",
  "properties": {
    "age":   { "type": "integer", "minimum": 0, "maximum": 150 },
    "score": { "type": "number",  "minimum": 0, "maximum": 5   },
    "count": { "type": "integer", "minimum": 1 }
  },
  "required": ["count"]
}
```

> クライアントの `validateObject` はフォーム入力の文字列を `Number()` で数値に変換してから検証する。`z.coerce.number()` を使う Zod 統合でも同様。
