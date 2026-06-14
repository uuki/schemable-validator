# カスタムバリデーション - 高度な利用例

---

## 概要

このプラグインは、フィールドの型・形式・文字数といった**構造的な制約**を PHP 側で一元定義し、JSON Schema を通じてクライアントと共有することを目的としています。

一方で、実際のフォームにはロケール・環境に固有の「原理的な検証」（電話番号の番号体系検証など）が必要になる場面もあります。こうした制約は JSON Schema では表現が難しいため、任意のバリデーションロジックを組み込める拡張ポイント（`SV::respect()`）を用意しています。

---

## 原理的な検証とは

JSON Schema (draft 2020-12) はフィールドの**構造と形式**を記述するための仕様であり、すべての検証ロジックを表現できるわけではありません。

たとえば次のような制約は、型・長さ・正規表現といったキーワードでは表現できない。

- 電話番号が実在する番号体系に属するか（国別の番号計画の検証）
- クレジットカード番号が Luhn アルゴリズムを満たすか
- IBAN が国コードと整合するか
- パスワードが十分な強度を持つか

これらは「文字列の形式チェック」ではなく、**ドメイン固有のルールや外部データベースに基づいた検証**であり、正規表現や JSON Schema のキーワードで近似することはできても、完全な表現は原理的に不可能です。

このプラグインでは、こうした制約を `SV::respect()` でラップし、JSON Schema 出力の `x-unmapped-fields` に記録する設計としています。

```
SV::respect($rule)
  │
  ├─ サーバー側: Respect/Validation でそのまま検証
  │
  └─ JSON Schema: x-unmapped-fields に記録（properties には含まれない）
       │
       └─ クライアント側: @schemable-validator/client / Zod で独自に追加検証
```

---

## 外部ライブラリとの結合パターン

JSON Schema で表現できない制約を実装する場合、バックエンドとフロントエンドでそれぞれ適切なライブラリを選び、このプラグインのエスケープハッチ（`SV::respect()`）を介して結合する。

### PHP 側（サーバー）

`SV::respect()` は Respect/Validation の `Validator` インスタンスを受け取る。`v::callback()` を使えば任意のロジック・外部ライブラリを注入できる。

```php
use Respect\Validation\Validator as v;

SV::respect(
  v::callback(function (mixed $value): bool {
    // ここに任意の検証ロジックを書く
    return someExternalLibrary::validate($value);
  })
)
```

サーバーが常に正の検証者となります。クライアント側の検証はあくまで UX 補助として扱います。

### JS 側（クライアント）

`x-unmapped-fields` に含まれるフィールドは `validateObject` が自動スキップする。クライアントでの検証が必要な場合は、**`@schemable-validator/client` の `Constraint`** または **Zod の `.superRefine()`** で追加する。

**`@schemable-validator/client` の場合:**

```typescript
import { type Constraint } from '@schemable-validator/client'

const checkCustomField: Constraint = (state) => {
  if (state.value === '') return state // optional フィールドの空入力は通す
  const ok = someJsLibrary.validate(state.value)
  return ok ? state : { ...state, errors: [...state.errors, 'エラーメッセージ'] }
}
```

**Zod の場合:**

```typescript
const schema = buildZodSchema(jsonSchema).extend({
  fieldName: z.string().optional().superRefine((val, ctx) => {
    if (!val) return
    if (!someJsLibrary.validate(val)) {
      ctx.addIssue({ code: 'custom', message: 'エラーメッセージ' })
    }
  }),
})
```

### 応用できるユースケース

| ユースケース | PHP ライブラリ | JS ライブラリ | JSON Schema |
|:--|:--|:--|:--|
| 電話番号 (E.164 / 国別) | `giggsey/libphonenumber-for-php` | `libphonenumber-js` | UNMAPPABLE |
| IBAN / 口座番号 | `globalcitizen/php-iban` | `ibantools` | UNMAPPABLE |
| クレジットカード (Luhn) | Respect `v::creditCard()` 組み込み | 独自 Luhn 実装 | UNMAPPABLE |
| 郵便番号 (国別) | `axlon/laravel-postal-code-validation` 等 | `postal-codes-js` | `pattern` で近似可 |
| パスワード強度 | カスタム callback | `zxcvbn` | UNMAPPABLE |

---

## ユースケース: 電話番号検証

電話番号は正規表現による近似では対応しきれない典型例である。`libphonenumber`（Google 製）は ITU-T E.164 に基づく国別番号体系のデータベースを持ち、実在する番号範囲を正確に検証できる。

### PHP 側 - `giggsey/libphonenumber-for-php`

#### インストール

```bash
composer require giggsey/libphonenumber-for-php
```

#### カスタム Respect ルール

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

#### SchemaBuilder への組み込み

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name'  => SV::string()->min(1)->max(100),
  'email' => SV::string()->email(),
  'tel'   => SV::respect(makePhoneRule('JP'))->optional(),
]);
```

#### JSON Schema 出力

`toJson()` を呼ぶと `tel` は `x-unmapped-fields` に現れる（`properties` には含まれない）。

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "properties": {
    "name":  { "type": "string", "minLength": 1, "maxLength": 100 },
    "email": { "type": "string", "format": "email" }
  },
  "required": ["name", "email"],
  "x-unmapped-fields": ["tel"]
}
```

---

### JS 側 (`@schemable-validator/client`) - `libphonenumber-js`

`@schemable-validator/client` の `Constraint` は `FieldState → FieldState` の純関数。外部ライブラリを直接ラップできる。

#### インストール

```bash
npm install libphonenumber-js
```

#### カスタム Constraint の実装

```typescript
import { isValidPhoneNumber } from 'libphonenumber-js'
import { type Constraint } from '@schemable-validator/client'

export const checkJapanesePhone: Constraint = (state) => {
  if (state.value === '') return state // optional フィールドの空入力は通す

  return isValidPhoneNumber(state.value, 'JP')
    ? state
    : { ...state, errors: [...state.errors, '有効な日本の電話番号を入力してください'] }
}
```

#### `validateObject` との合成

```typescript
import { validateObject } from '@schemable-validator/client'
import { checkJapanesePhone } from './constraints/phone'

async function validate(data: Record<string, string>, jsonSchema: ObjectSchema) {
  const result = { ...validateObject(data, jsonSchema) }

  // x-unmapped-fields のフィールドを追加検証
  if ((jsonSchema['x-unmapped-fields'] ?? []).includes('tel')) {
    const state = checkJapanesePhone({ value: data['tel'] ?? '', errors: [] })
    result['tel'] = {
      value:    state.value,
      is_valid: state.errors.length === 0,
      errors:   state.errors.length > 0 ? state.errors : null,
    }
  }

  return result
}
```

---

### JS 側 (Zod) - `libphonenumber-js`

```typescript
import { z } from 'zod'
import { isValidPhoneNumber } from 'libphonenumber-js'

const contactSchema = buildZodSchema(jsonSchema).extend({
  tel: z.string().optional().superRefine((val, ctx) => {
    if (!val) return
    if (!isValidPhoneNumber(val, 'JP')) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: '有効な日本の電話番号を入力してください',
      })
    }
  }),
})
```
