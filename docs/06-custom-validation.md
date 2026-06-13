# カスタムバリデーション — 高度な利用例

JSON Schema (draft 2020-12) で表現できない制約（電話番号、IBAN、クレジットカード Luhn チェックなど）を扱うパターンを説明する。  
電話番号を例に、バックエンド・フロントエンドそれぞれで外部ライブラリを使う最小実装を示す。

---

## 概要

```
PHP (SchemaBuilder)
  └─ SV::respect($customRule)    ← 任意の Respect ルール / 外部ライブラリをラップ
        │
        ▼
  JSON Schema output
  └─ "x-unmapped-fields": ["tel"]  ← 表現不可フィールドはここに記録される
        │
        ▼
  フロントエンド
  └─ SDK: Constraint で独自ライブラリを合成
     Zod: .superRefine() で独自ライブラリを注入
```

原理的な検証は schema 上では `x-unmapped-fields` として返却を行い、各領域における検証の実装を前提とする。SDK 利用時は、これらを自動的に「クライアント検証スキップ → サーバー委譲」として扱う。

---

## PHP 側 — `giggsey/libphonenumber-for-php`

### インストール

```bash
composer require giggsey/libphonenumber-for-php
```

### カスタム Respect ルール

```php
use Respect\Validation\Validator as v;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;

/**
 * 地域コードを指定した libphonenumber ベースの電話番号バリデーター。
 * $region = null のときは E.164 形式 (+81...) を要求する。
 */
function makePhoneRule(string $region = null): \Respect\Validation\Validator {
  $util = PhoneNumberUtil::getInstance();

  return v::callback(function (mixed $value) use ($util, $region): bool {
    if (!is_string($value) || $value === '') {
      return false;
    }
    try {
      $number = $util->parse($value, $region);
      return $region !== null
        ? $util->isValidNumberForRegion($number, $region)
        : $util->isValidNumber($number);
    } catch (NumberParseException) {
      return false;
    }
  });
}
```

### SchemaBuilder への組み込み

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name'  => SV::string()->min(1)->max(100),
  'email' => SV::string()->email(),
  // SV::respect() は JSON Schema 変換不可 → x-unmapped-fields に記録される
  'tel'   => SV::respect(makePhoneRule('JP'))->optional(),
]);
```

### JSON Schema 出力

`toJson()` を呼ぶと `tel` は `x-unmapped-fields` に現れる（`properties` には含まれない）。

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "properties": {
    "name":  { "type": "string", "minLength": 1, "maxLength": 100 },
    "email": { "type": "string", "format": "email" }
  },
  "required": ["name", "email", "tel"],
  "x-unmapped-fields": ["tel"]
}
```

> **注意**: `optional()` を付けた場合でも `required` から除外されるだけで、バリデーションロジック自体は PHP 側で実行される。

---

## JS 側 (SDK) — `libphonenumber-js`

SDK の `Constraint` は `FieldState → FieldState` の純関数。外部ライブラリを直接ラップできる。

### インストール

```bash
npm install libphonenumber-js
```

### カスタム Constraint の実装

```typescript
import { isValidPhoneNumber } from 'libphonenumber-js'
import { type Constraint } from '@schemable-validator/sdk'

export const checkJapanesePhone: Constraint = (state) => {
  // 空文字は optional フィールドの「未入力」なので通す
  if (state.value === '') return state

  return isValidPhoneNumber(state.value, 'JP')
    ? state
    : { ...state, errors: [...state.errors, '有効な日本の電話番号を入力してください'] }
}
```

### SDK の `validateObject` + 追加 Constraint の合成

`x-unmapped-fields` に入ったフィールドはスキーマに `properties` がないため、
`validateObject` は「クライアント検証スキップ」として扱う。
そのフィールドだけ手動で追加検証する。

```typescript
import { validateObject, composeConstraints, constraintsFromSchema } from '@schemable-validator/sdk'
import { checkJapanesePhone } from './constraints/phone'

async function validate(data: Record<string, string>, jsonSchema: ObjectSchema) {
  // 1. JSON Schema で表現されたフィールドを一括検証
  const result = validateObject(data, jsonSchema)

  // 2. x-unmapped-fields (tel など) を追加検証
  const unmapped = jsonSchema['x-unmapped-fields'] ?? []

  const extendedResult = { ...result }

  if (unmapped.includes('tel') && data['tel'] !== undefined) {
    const state = checkJapanesePhone({ value: data['tel'], errors: [] })
    extendedResult['tel'] = {
      value:    state.value,
      is_valid: state.errors.length === 0,
      errors:   state.errors.length > 0 ? state.errors : null,
    }
  }

  return extendedResult
}
```

---

## JS 側 (Zod) — `libphonenumber-js`

スキーマを Zod で構築している場合は `.superRefine()` を使う。

```typescript
import { z } from 'zod'
import { isValidPhoneNumber } from 'libphonenumber-js'

// buildZodSchema() で生成した既存のスキーマに tel フィールドを追加
const contactSchema = buildZodSchema(jsonSchema).extend({
  // x-unmapped-fields なので buildZodSchema には入っていない
  // required かどうかに応じて optional() を付ける
  tel: z.string().optional().superRefine((val, ctx) => {
    if (!val) return // optional + 空文字 = OK
    if (!isValidPhoneNumber(val, 'JP')) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: '有効な日本の電話番号を入力してください',
      })
    }
  }),
})
```


---

## 一般化 — 他のライブラリへの応用

同じパターンは電話番号以外にも使える。

| ユースケース | PHP ライブラリ | JS ライブラリ | JSON Schema |
|:--|:--|:--|:--|
| 電話番号 (E.164 / 国別) | `giggsey/libphonenumber-for-php` | `libphonenumber-js` | UNMAPPABLE |
| IBAN / 口座番号 | `globalcitizen/php-iban` | `ibantools` | UNMAPPABLE |
| クレジットカード (Luhn) | Respect `v::creditCard()` 組み込み | 独自 Luhn 実装 | UNMAPPABLE |
| 郵便番号 (国別) | `axlon/laravel-postal-code-validation` 等 | `postal-codes-js` | `pattern` で近似可 |
| パスワード強度 | カスタム callback | `zxcvbn` | UNMAPPABLE |

