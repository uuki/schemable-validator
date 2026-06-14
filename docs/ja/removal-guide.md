# Removal Guide

このガイドは schemable-validator プラグインの **SchemaBuilder / Validator コア** を取り除き、各スタック本来のライブラリへ移行する手順を説明します。

**スコープ:** `SV::` ファサード・`SchemaBuilder`・`Validator` クラス（クライアントパッケージを含む）。WordPress ヘルパー・`Template`・CSRF・reCAPTCHA は対象外です。

> **バージョン前提**  
> PHP 側は Respect/Validation **2.x** を対象とします。将来このプラグインの依存バージョンが 3.x に上がった際は、別途 Respect/Validation 3.x 移行ガイドを追加予定です。

---

## 目次

1. [PHP - SchemaBuilder を Respect/Validation 2.x へ移行](#1-php--schemabuilder-を-respectvalidation-2x-へ移行)
   - [型マッピング早見表](#11-型マッピング早見表)
   - [Validator の直接利用](#12-validator-の直接利用)
   - [optional / nullable の扱い](#13-optional--nullable-の扱い)
   - [when() 条件分岐の置き換え](#14-when-条件分岐の置き換え)
   - [バリデーション結果の形式変更](#15-バリデーション結果の形式変更)
2. [クライアント - TypeScript/JavaScript を Zod へ移行](#2-クライアント--typescriptjavascript-を-zod-へ移行)
   - [型マッピング早見表](#21-型マッピング早見表)
   - [validateObject → Zod safeParse](#22-validateobject--zod-safeparse)
   - [when() の Zod への置き換え](#23-when-の-zod-への置き換え)
   - [その他のライブラリ](#24-その他のライブラリ)

---

## 1. PHP - SchemaBuilder を Respect/Validation 2.x へ移行

### 1.1 型マッピング早見表

| SV API | Respect/Validation 2.x |
|:--|:--|
| `SV::string()` | `v::stringType()` |
| `SV::integer()` | `v::intType()` |
| `SV::number()` | `v::numericVal()` |
| `SV::boolean()` | `v::boolType()` |
| `SV::string()->length($min, $max)` | `v::stringType()->length($min, $max)` |
| `SV::string()->min($n)` | `v::stringType()->length($n, null)` |
| `SV::string()->max($n)` | `v::stringType()->length(null, $n)` |
| `SV::integer()->min($n)` | `v::intType()->min($n)` |
| `SV::integer()->max($n)` | `v::intType()->max($n)` |
| `SV::number()->min($n)` | `v::numericVal()->min($n)` |
| `SV::number()->max($n)` | `v::numericVal()->max($n)` |
| `SV::string()->email()` | `v::email()` |
| `SV::string()->url()` | `v::url()` |
| `SV::string()->pattern('...')` | `v::regex('/pattern/u')` |
| `SV::string()->date()` | `v::date('Y-m-d')` |
| `SV::string()->dateTime()` | `v::dateTime()` |
| `SV::string()->time()` | `v::time('H:i:s')` |
| `SV::string()->uuid()` | `v::uuid()` |
| `SV::string()->ipv4()` | `v::ip('*', FILTER_FLAG_IPV4)` |
| `SV::string()->ipv6()` | `v::ip('*', FILTER_FLAG_IPV6)` |
| `SV::string()->slug()` | `v::slug()` |
| `SV::string()->domain()` | `v::domain()` |
| `SV::enum(['a', 'b'])` | `v::in(['a', 'b'])` |
| `SV::array(SV::string())` | `v::each(v::stringType())` |
| `SV::respect(v::...)` | `v::...` をそのまま使用 |
| `SV::postalCode('JP')` | `v::postalCode('JP')` |
| `SV::creditCard()` | `v::creditCard()` |
| `SV::iban()` | `v::iban()` |

### 1.2 Validator の直接利用

**Before:**

```php
use SchemableValidator\SV;

$schema = SV::object([
    'name'  => SV::string()->length(1, 100),
    'email' => SV::string()->email(),
    'age'   => SV::integer()->min(0)->max(150)->optional(),
]);

$validator = $schema->toValidator();
$result    = $validator->validate($_POST)->getResult();
```

**After:**

```php
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;

$schema = [
    'name'  => v::stringType()->length(1, 100),
    'email' => v::email(),
    'age'   => v::optional(v::intType()->min(0)->max(150)),
];

$result = [];
foreach ($schema as $field => $rule) {
    $value = $_POST[$field] ?? null;
    try {
        $rule->assert($value);
        $result[$field] = ['value' => $value, 'is_valid' => true,  'errors' => null];
    } catch (NestedValidationException $e) {
        $result[$field] = ['value' => $value, 'is_valid' => false, 'errors' => $e->getFullMessage()];
    }
}
```

> **array フィールド**  
> `SV::array(SV::string())->minItems(1)->maxItems(5)` は Respect/Validation 2.x に直接対応するルールがありません。`v::each(v::stringType())` で要素検証を行い、要素数のチェックは PHP で手動で行ってください。
>
> ```php
> $items = $_POST['tags'] ?? [];
> if (!is_array($items) || count($items) < 1 || count($items) > 5) {
>     $result['tags'] = ['value' => $items, 'is_valid' => false, 'errors' => '- tags must have 1 to 5 items'];
> } else {
>     try {
>         v::each(v::stringType())->assert($items);
>         $result['tags'] = ['value' => $items, 'is_valid' => true, 'errors' => null];
>     } catch (NestedValidationException $e) {
>         $result['tags'] = ['value' => $items, 'is_valid' => false, 'errors' => $e->getFullMessage()];
>     }
> }
> ```

### 1.3 optional / nullable の扱い

| SV | Respect/Validation 2.x |
|:--|:--|
| `SV::string()->optional()` | `v::optional(v::stringType())` |
| `SV::string()->nullable()` | `v::nullable(v::stringType())` |
| `SV::string()->optional()->nullable()` | `v::nullable(v::optional(v::stringType()))` |

`v::optional()` は `null` と空文字列 `''` をスキップします。`v::nullable()` は `null` をスキップしますが `''` は検証されます。

### 1.4 when() 条件分岐の置き換え

`when()` は Respect/Validation 2.x にネイティブな対応ルールがありません。**バリデーション後に PHP の条件分岐で実装**してください。

#### パターン A - 単純な等値条件

```php
// Before
SV::object([
    'type'         => SV::enum(['personal', 'company']),
    'company_name' => SV::string()->length(1, 100)->optional(),
])->when('type', 'company', ['company_name']);

// After
$schema = [
    'type'         => v::in(['personal', 'company']),
    'company_name' => v::optional(v::stringType()->length(1, 100)),
];

// 1. 通常バリデーションを実行
$result = validateFields($schema, $_POST); // 前節のループ関数

// 2. 条件分岐を後追いで適用
if (($_POST['type'] ?? null) === 'company') {
    $val = $_POST['company_name'] ?? null;
    if ($val === null || $val === '') {
        $result['company_name'] = [
            'value'    => $val,
            'is_valid' => false,
            'errors'   => '- company_name is required',
        ];
    }
}
```

#### パターン B - 不等値条件 (SV::notEqual)

```php
// Before
SV::object([
    'role'        => SV::string(),
    'permissions' => SV::array(SV::string())->optional(),
])->when('role', SV::notEqual('guest'), ['permissions']);

// After
if (($_POST['role'] ?? null) !== 'guest') {
    $val = $_POST['permissions'] ?? null;
    if ($val === null || $val === '' || $val === []) {
        $result['permissions'] = [
            'value'    => $val,
            'is_valid' => false,
            'errors'   => '- permissions is required',
        ];
    }
}
```

#### パターン C - 数値比較条件 (SV::greaterThanOrEqual 等)

```php
// Before
SV::object([
    'age'      => SV::integer()->min(0)->max(150),
    'guardian' => SV::string()->length(1, 100)->optional(),
])->when('age', SV::lessThan(18), ['guardian']);

// After
$age = (int) ($_POST['age'] ?? 0);
if ($age < 18) {
    $val = $_POST['guardian'] ?? null;
    if ($val === null || $val === '') {
        $result['guardian'] = [
            'value'    => $val,
            'is_valid' => false,
            'errors'   => '- guardian is required',
        ];
    }
}
```

| SV 条件式 | PHP 等価条件 |
|:--|:--|
| `SV::equal('x')` または スカラー直接指定 | `=== 'x'` |
| `SV::notEqual('x')` | `!== 'x'` |
| `SV::greaterThanOrEqual(18)` | `>= 18` (数値キャスト後) |
| `SV::lessThanOrEqual(100)` | `<= 100` (数値キャスト後) |
| `SV::greaterThan(0)` | `> 0` (数値キャスト後) |
| `SV::lessThan(18)` | `< 18` (数値キャスト後) |

#### パターン D - フィールド間参照 (SV::field)

```php
// Before
SV::object([
    'password'         => SV::string()->length(8, 255),
    'password_confirm' => SV::string()->optional(),
])->when('password', SV::notEqual(SV::field('password_confirm')), ['password_confirm']);
// ※ これは「password と password_confirm が異なる場合に password_confirm を必須にする」という
//    パターンであり、実際には「2 つのフィールドが一致すること」を検証する方が自然です。

// After - 一致検証に置き換える
$pw  = $_POST['password']         ?? '';
$pwc = $_POST['password_confirm'] ?? '';
if ($pw !== $pwc) {
    $result['password_confirm'] = [
        'value'    => $pwc,
        'is_valid' => false,
        'errors'   => '- password_confirm must match password',
    ];
}
```

#### 複数条件のまとめ方

複数の `when()` 条件が連鎖する場合は、まとめて後処理関数として抽出することを推奨します。

```php
function applyConditionals(array &$result, array $data): void
{
    // 条件 1: type === 'company' のとき company_name 必須
    if (($data['type'] ?? null) === 'company') {
        requireField($result, $data, 'company_name');
    }

    // 条件 2: age < 18 のとき guardian 必須
    if ((int) ($data['age'] ?? 0) < 18) {
        requireField($result, $data, 'guardian');
    }
}

function requireField(array &$result, array $data, string $field): void
{
    $val = $data[$field] ?? null;
    if ($val === null || $val === '' || $val === []) {
        $result[$field] = [
            'value'    => $val,
            'is_valid' => false,
            'errors'   => "- {$field} is required",
        ];
    }
}
```

### 1.5 バリデーション結果の形式変更

SV の `getResult()` は次の形式を返します。

```php
[
    'field_name' => [
        'value'    => mixed,   // 入力値そのまま
        'is_valid' => bool,
        'errors'   => string|null,  // Respect/Validation のフルメッセージまたは null
    ],
    // ...
]
```

Respect/Validation を直接使う場合、この形式は存在しません。結果をフロントエンドや後続処理に渡している場合はその呼び出し元も合わせて変更してください。

---

## 2. クライアント - TypeScript/JavaScript を Zod へ移行

SV のクライアントパッケージは `SchemaBuilder::toJsonSchema()` が出力した JSON Schema を受け取り、フォームフィールドを検証します。Zod へ移行する場合は JSON Schema 経由のアダプター層をなくし、Zod スキーマをそのまま定義します。

```bash
npm install zod
# または
pnpm add zod
```

### 2.1 型マッピング早見表

| SV (PHP) | Zod |
|:--|:--|
| `SV::string()` | `z.string()` |
| `SV::string()->length(1, 100)` | `z.string().min(1).max(100)` |
| `SV::string()->email()` | `z.string().email()` |
| `SV::string()->url()` | `z.string().url()` |
| `SV::string()->pattern('[a-z]+')` | `z.string().regex(/^[a-z]+$/u)` |
| `SV::string()->date()` | `z.string().date()` (Zod 3.23+) |
| `SV::string()->uuid()` | `z.string().uuid()` |
| `SV::integer()` | `z.number().int()` |
| `SV::integer()->min(0)->max(150)` | `z.number().int().min(0).max(150)` |
| `SV::number()` | `z.number()` |
| `SV::boolean()` | `z.boolean()` |
| `SV::enum(['a', 'b'])` | `z.enum(['a', 'b'])` |
| `SV::array(SV::string())` | `z.array(z.string())` |
| `SV::array(SV::string()).minItems(1)` | `z.array(z.string()).min(1)` |
| `SV::string()->optional()` | フィールド自体を `.optional()` にする |
| `SV::string()->nullable()` | `z.string().nullable()` |

### 2.2 validateObject → Zod safeParse

**Before:**

```typescript
import { validateObject, isAllValid } from '@uuki/schemable-validator-client'
import type { ObjectSchema } from '@uuki/schemable-validator-client'

// SchemaBuilder::toJsonSchema() で生成したスキーマを fetch して使用
const schema: ObjectSchema = await fetchSchema('/api/schema')

const result = validateObject(formData, schema)
if (isAllValid(result)) {
  // 送信処理
}
```

**After:**

```typescript
import { z } from 'zod'

const schema = z.object({
  name:  z.string().min(1).max(100),
  email: z.string().email(),
  age:   z.number().int().min(0).max(150).optional(),
})

const result = schema.safeParse(formData)
if (result.success) {
  // 送信処理（result.data は型付き）
} else {
  // result.error.issues でエラー詳細を取得
  const errors = Object.fromEntries(
    result.error.issues.map((issue) => [issue.path.join('.'), issue.message])
  )
}
```

### 2.3 when() の Zod への置き換え

#### パターン A - `superRefine` を使う方法

最も汎用的なアプローチです。複数条件・フィールド間参照にも対応します。

```typescript
import { z } from 'zod'

// Before (SV):
// SV::object([...]).when('type', 'company', ['company_name'])

// After (Zod):
const schema = z.object({
  type:         z.enum(['personal', 'company']),
  company_name: z.string().min(1).max(100).optional(),
}).superRefine((data, ctx) => {
  if (data.type === 'company' && !data.company_name) {
    ctx.addIssue({
      code:    'custom',
      path:    ['company_name'],
      message: 'company_name is required when type is company',
    })
  }
})
```

#### パターン B - `z.discriminatedUnion` を使う方法

フィールド値による型の分岐が明確な場合、型安全性が高くなります。

```typescript
// Before (SV):
// SV::object([
//   'type'         => SV::enum(['personal', 'company']),
//   'company_name' => SV::string()->length(1, 100)->optional(),
// ])->when('type', 'company', ['company_name'])

// After (Zod):
const schema = z.discriminatedUnion('type', [
  z.object({
    type: z.literal('personal'),
  }),
  z.object({
    type:         z.literal('company'),
    company_name: z.string().min(1).max(100),
  }),
])
```

#### パターン C - 数値比較条件

```typescript
// Before (SV):
// .when('age', SV::lessThan(18), ['guardian'])

// After (Zod):
const schema = z.object({
  age:      z.number().int().min(0).max(150),
  guardian: z.string().min(1).max(100).optional(),
}).superRefine((data, ctx) => {
  if (data.age < 18 && !data.guardian) {
    ctx.addIssue({
      code:    'custom',
      path:    ['guardian'],
      message: 'guardian is required when age is under 18',
    })
  }
})
```

#### パターン D - フィールド間参照 (パスワード確認など)

```typescript
// Before (SV):
// .when('password', SV::notEqual(SV::field('password_confirm')), ['password_confirm'])

// After (Zod):
const schema = z.object({
  password:         z.string().min(8).max(255),
  password_confirm: z.string(),
}).superRefine((data, ctx) => {
  if (data.password !== data.password_confirm) {
    ctx.addIssue({
      code:    'custom',
      path:    ['password_confirm'],
      message: 'passwords do not match',
    })
  }
})
```

#### 複数条件の連鎖

`superRefine` は一つのスキーマに複数の条件を書けます。

```typescript
const schema = z.object({
  type:         z.enum(['personal', 'company']),
  company_name: z.string().optional(),
  age:          z.number().int().min(0),
  guardian:     z.string().optional(),
}).superRefine((data, ctx) => {
  if (data.type === 'company' && !data.company_name) {
    ctx.addIssue({
      code: 'custom',
      path: ['company_name'],
      message: 'required when type is company',
    })
  }
  if (data.age < 18 && !data.guardian) {
    ctx.addIssue({
      code: 'custom',
      path: ['guardian'],
      message: 'required when age is under 18',
    })
  }
})
```

### 2.4 その他のライブラリ

Zod 以外を使う場合の参考情報です。

#### Valibot

Zod と同様の宣言的スキーマをより軽量に実現します。バンドルサイズを重視する場合に適しています。

```typescript
import * as v from 'valibot'

const schema = v.object({
  name:  v.pipe(v.string(), v.minLength(1), v.maxLength(100)),
  email: v.pipe(v.string(), v.email()),
  type:  v.picklist(['personal', 'company']),
})

// when() の代替 - check で条件付き必須を実装
const schemaWithConditional = v.pipe(
  schema,
  v.check(
    (data) => data.type !== 'company' || !!data.company_name,
    'company_name is required when type is company',
  ),
)

const result = v.safeParse(schemaWithConditional, formData)
```

#### Yup

`when()` をネイティブにサポートしており、SV の条件構造に近い書き方ができます。

```typescript
import * as yup from 'yup'

const schema = yup.object({
  type:         yup.string().oneOf(['personal', 'company']).required(),
  company_name: yup.string().when('type', {
    is:        'company',
    then:      (s) => s.min(1).max(100).required(),
    otherwise: (s) => s.optional(),
  }),
})
```

---

## 補足: toJsonSchema() / JSON Schema の扱い

`SchemaBuilder::toJsonSchema()` が出力する JSON Schema は標準の draft 2020-12 に準拠しています（`x-when` は拡張キーです）。別の JSON Schema バリデーターと組み合わせて使い続けることもできますが、Zod などのライブラリを使う場合は JSON Schema は不要になるため削除して構いません。

---

## 関連ドキュメント

- [Respect/Validation 2.x - List of Rules](https://respect-validation.readthedocs.io/en/2.4/08-list-of-rules-by-category/)
- [Respect/Validation 2.x → 3.x Migration Guide](https://github.com/Respect/Validation/blob/main/docs/migration-guide.md) *(3.x 移行時に参照)*
- [Zod ドキュメント](https://zod.dev)
- [Valibot ドキュメント](https://valibot.dev)
