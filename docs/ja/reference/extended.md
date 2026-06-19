# SV::file() / SV::custom() / SV::respect() - JSON Schema 非対応型

これらの型はサーバー側検証を行いますが、JSON Schema に変換できません。
`SV::file()` は NativeFileValidator を使用します（依存なし）。`SV::custom()` は任意の callable を受け取ります（依存なし）。`SV::respect()` はオプションの Respect/Validation ライブラリを使用します。
`toJsonSchema()` の出力では `properties` に含まれず、`x-unmapped-fields` にフィールド名が記録されます。

クライアントの `validateObject` はこれらのフィールドを自動的にスキップし、サーバー側に委譲します。

---

## SV::file(accept) {#file}

ファイルアップロードの MIME タイプを検証します。

```php
SV::file(array $accept = [])
```

| パラメータ | 型 | 説明 |
|:--|:--|:--|
| `$accept` | `string[]` | 許容する MIME タイプの配列 |

**用途:** `<input type="file">` のファイル種別を制限する。

```php
// 画像のみ
SV::file(['image/jpeg', 'image/png', 'image/webp'])

// PDF のみ、任意入力
SV::file(['application/pdf'])->optional()

// 種別不問（存在チェックのみ）
SV::file()
```

### サーバー側での使い方

```php
$schema = SV::object([
  'name'   => SV::string()->min(1),
  'avatar' => SV::file(['image/jpeg', 'image/png'])->optional(),
]);

// ファイルの検証は validateFiles() を使う
$result = $schema->toValidator()
  ->validate($_POST)
  ->validateFiles($_FILES)
  ->getResult();
```

### JSON Schema 出力

`avatar` は `properties` に含まれず `x-unmapped-fields` に記録される。

```json
{
  "type": "object",
  "properties": {
    "name": { "type": "string", "minLength": 1 }
  },
  "required": ["name"],
  "x-unmapped-fields": ["avatar"]
}
```

---

## SV::custom(callable, message) {#custom}

依存なしのエスケープハッチ。任意の callable 述語で検証ロジックを指定できる。組み込み型では表現できない制約に使う。

```php
SV::custom(callable $predicate, string $message = 'Validation failed')
```

| パラメータ | 型 | 説明 |
|:--|:--|:--|
| `$predicate` | `callable` | フィールド値を受け取り `bool` を返す callable |
| `$message` | `string` | 検証失敗時のエラーメッセージ（デフォルト: `'Validation failed'`） |

JSON Schema では表現できないため `x-unmapped-fields` に記録されます。

**用途:** 電話番号検証、カスタム業務ルール、任意の PHP ライブラリとの連携 -- Respect/Validation を依存に含めずに利用可能。

```php
// シンプルなインライン述語
SV::custom(
  fn(mixed $value): bool => is_string($value) && strlen($value) === 8 && ctype_digit($value),
  '8桁の数字を入力してください'
)->optional()

// 外部ライブラリのラップ
SV::custom(
  fn(mixed $value): bool => SomeLibrary::validate($value),
  '無効な値です'
)
```

### 外部ライブラリとの連携

```php
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;

$phoneUtil = PhoneNumberUtil::getInstance();

$schema = SV::object([
  'tel' => SV::custom(
    function ($value) use ($phoneUtil) {
      try {
        $number = $phoneUtil->parse($value, 'JP');
        return $phoneUtil->isValidNumberForRegion($number, 'JP');
      } catch (NumberParseException) {
        return false;
      }
    },
    '有効な日本の電話番号を入力してください'
  )->optional(),
]);
```

詳細は [高度な利用例](/ja/custom-validation) を参照。

### JSON Schema 出力

```json
{
  "type": "object",
  "properties": {},
  "x-unmapped-fields": ["tel"]
}
```

---

## SV::postalCode(countryCode) {#postalcode}

> **@deprecated** -- `RespectRules` に移動。代わりに `SV::custom()` + 郵便番号検証ライブラリを使用してください。

国別の**郵便番号**を検証する。Respect/Validation の `postalCode()` ルールをラップしたショートハンド。

```php
SV::postalCode(string $countryCode)
```

| パラメータ | 型 | 説明 |
|:--|:--|:--|
| `$countryCode` | `string` | ISO 3166-1 alpha-2 国コード（例: `'JP'`, `'US'`, `'DE'`） |

JSON Schema では表現できないため `x-unmapped-fields` に記録されます。

```php
SV::postalCode('JP')->optional()  // 日本の郵便番号（任意入力）
SV::postalCode('US')              // 米国の ZIP コード
```

`SV::respect(v::postalCode('JP'))` の糖衣構文です。

---

## SV::creditCard(...brands) {#creditcard}

> **@deprecated** -- `RespectRules` に移動。代わりに `SV::custom()` + Luhn アルゴリズムライブラリを使用してください。

**クレジットカード番号**を Luhn アルゴリズムで検証します。

```php
SV::creditCard(string ...$brands)
```

| パラメータ | 型 | 説明 |
|:--|:--|:--|
| `...$brands` | `string` | 受け入れるカードブランド（省略時は全ブランド対応）。例: `'Visa'`, `'Mastercard'` |

JSON Schema では表現できないため `x-unmapped-fields` に記録されます。

```php
SV::creditCard()                    // 全ブランド
SV::creditCard('Visa', 'Mastercard') // Visa / Mastercard のみ
```

---

## SV::iban() {#iban}

> **@deprecated** -- `RespectRules` に移動。代わりに `SV::custom()` + IBAN 検証ライブラリを使用してください。

**IBAN**（国際銀行口座番号）を検証する。

```php
SV::iban()
```

JSON Schema では表現できないため `x-unmapped-fields` に記録されます。

```php
SV::iban()->optional()
```

---

## SV::respect(rule) {#respect}

> **@deprecated** -- 依存なしの代替として `SV::custom(callable, message)` を使用してください。このメソッドにはオプションの `respect/validation` パッケージが必要です。

Respect/Validation のルールを直接指定するエスケープハッチです。組み込み型では表現できない制約に使います。

```php
SV::respect(Respect\Validation\Validator $rule)
```

| パラメータ | 型 | 説明 |
|:--|:--|:--|
| `$rule` | `Respect\Validation\Validator` | Respect のバリデーターインスタンス |

**用途:** libphonenumber を使った電話番号検証、IBAN 検証、カスタム業務ルールなど、JSON Schema では表現できない制約。

```php
use Respect\Validation\Validator as v;

// Respect 組み込みのクレジットカード検証
SV::respect(v::creditCard())

// callback でカスタムロジックを注入
SV::respect(v::callback(function ($value) {
  return strlen($value) === 8 && ctype_digit($value);
}))->optional()
```

### 外部ライブラリとの連携

```php
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;

$phoneUtil = PhoneNumberUtil::getInstance();

$schema = SV::object([
  'tel' => SV::respect(
    v::callback(function ($value) use ($phoneUtil) {
      try {
        $number = $phoneUtil->parse($value, 'JP');
        return $phoneUtil->isValidNumberForRegion($number, 'JP');
      } catch (NumberParseException) {
        return false;
      }
    })
  )->optional(),
]);
```

詳細は [高度な利用例](/ja/custom-validation) を参照。

### JSON Schema 出力

```json
{
  "type": "object",
  "properties": {},
  "x-unmapped-fields": ["tel"]
}
```

---

## x-unmapped-fields の扱い

クライアント側で `x-unmapped-fields` を追加検証するには、クライアントの `Constraint` または Zod の `.superRefine()` を使う。

```typescript
// client: 手動で追加検証
const result = validateObject(data, schema)
const unmapped = schema['x-unmapped-fields'] ?? []

if (unmapped.includes('tel')) {
  const ok = /^0\d{9,10}$/.test(data.tel ?? '')
  // result を拡張して tel の検証結果を追加
}
```

```typescript
// Zod: superRefine で追加
const zodSchema = buildZodSchema(schema).extend({
  tel: z.string().optional().superRefine((val, ctx) => {
    if (!val) return
    if (!/^0\d{9,10}$/.test(val)) {
      ctx.addIssue({ code: 'custom', message: '有効な電話番号を入力してください' })
    }
  }),
})
```
