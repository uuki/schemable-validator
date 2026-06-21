# Removal Guide

This guide explains how to remove schemable-validator and migrate to standalone validation.

**Scope:** The `SV::` facade, `SchemaBuilder`, `Validator`, the TypeScript client package, and the WordPress plugin.
WordPress helpers (`schv_csrf()`, `schv_template()`, `schv_form()`) and CAPTCHA drivers are out of scope.

---

## Table of Contents

1. [Removal steps](#1-removal-steps)
2. [PHP - Plain PHP validation (no library)](#2-php--plain-php-validation-no-library)
3. [PHP - Migrating to Respect/Validation 2.x](#3-php--migrating-to-respectvalidation-2x)
4. [Client - Defining validation rules independently](#4-client--defining-validation-rules-independently)
5. [Client - Migrating to Zod](#5-client--migrating-to-zod)

---

## 1. Removal steps

### 1.1 Backend

1. **List all call sites.**
   Search for `SV::`, `SchemaBuilder`, `Validator::fromJsonSchema`, `schv_register_schema`, `schv_stored_schema`, `->toValidator()`, and `->toJsonSchema()`.
   Each call site is a validation boundary that needs a replacement.

2. **Replace each call site** with either plain PHP validation (section 2) or a library of your choice (section 3).
   Work one form at a time: replace the schema definition, the `validate()` call, and the result consumption.

3. **Remove the package.**

   ```shell
   composer remove uuki/schemable-validator
   ```

4. **Remove WordPress plugin registration** (if applicable).
   Deactivate the plugin in WP Admin, then delete `packages/wp-schemable-validator/` or its deploy target.
   Remove any `schv_register_schema()` calls, Schema Editor stored schemas (`wp_options` keys prefixed `schv_schema_`), and the `schv-schemas/` directory in the active theme.

5. **Remove CSRF shims** (if applicable).
   If your code uses `schv_csrf()`, replace with WordPress nonces (`wp_create_nonce` / `wp_verify_nonce`) or a standalone CSRF library.

### 1.2 Client

Removing the PHP package also removes the JSON Schema REST endpoint (`/wp-json/schv/v1/schema/*`).
Any client code that fetches a schema from that endpoint and converts it to Zod or Valibot stops working.

Client-side validation rules were derived from the PHP schema at runtime.
After removal, the two stacks have no shared schema source.
Each validation rule must be defined independently in the client codebase.

1. **Search for `@uuki/schemable-validator-client` imports** and `schv/v1/schema` fetch URLs.

2. **Define the same rules in client code** using Zod, Valibot, or another library (section 5).
   The type mapping tables in section 5 show the correspondence.

3. **Remove the client package.**

   ```shell
   npm uninstall @uuki/schemable-validator-client
   ```

4. **Establish a maintenance rule.**
   Any future change to a server-side validation rule (adding a field, changing a constraint) must be manually reflected in the client schema.
   Without the shared JSON Schema, this synchronisation is the developer's responsibility.

---

## 2. PHP - Plain PHP validation (no library)

When no third-party validation library is needed, replace `SV::object([...])->toValidator()->validate()` with a validation function that uses PHP built-in functions.

### 2.1 General pattern

```php
function validateFields(array $rules, array $data): array {
    $result = [];
    foreach ($rules as $field => $rule) {
        $value  = $data[$field] ?? '';
        $errors = $rule($value);
        $result[$field] = [
            'value'    => $value,
            'is_valid' => $errors === [],
            'errors'   => $errors === [] ? null : implode(', ', $errors),
        ];
    }
    return $result;
}
```

Each `$rule` is a closure that returns an array of error messages (empty array on success).

### 2.2 Translating SV constraints

```php
$rules = [
    'name' => function ($v) {
        if ($v === '') return ['name is required'];
        if (mb_strlen($v) < 2)   return ['name must be at least 2 characters'];
        if (mb_strlen($v) > 50)  return ['name must be at most 50 characters'];
        return [];
    },
    'email' => function ($v) {
        if ($v === '') return ['email is required'];
        if (filter_var($v, FILTER_VALIDATE_EMAIL) === false) return ['invalid email'];
        return [];
    },
    'tel' => function ($v) {
        // optional field — empty is valid
        if ($v === '') return [];
        if (!preg_match('/^(0\d{9,10}|0\d{1,4}-\d{1,4}-\d{3,4})$/u', $v)) {
            return ['invalid phone number format'];
        }
        return [];
    },
    'type' => function ($v) {
        if (!in_array($v, ['general', 'support', 'sales', 'other'], true)) {
            return ['invalid type'];
        }
        return [];
    },
    'body' => function ($v) {
        if ($v === '') return ['body is required'];
        if (mb_strlen($v) < 10) return ['body must be at least 10 characters'];
        return [];
    },
];

$result = validateFields($rules, $_POST);
```

### 2.3 Conditional requirements (when)

Handle conditional requirements as a post-processing step, just as in the Respect/Validation migration path (section 3.4).

### 2.4 File validation

`SV::file()` wraps MIME type and size checks.
Replace with `finfo_file()` for MIME detection and `$_FILES[...]['size']` for size limits.

```php
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($_FILES['avatar']['tmp_name']);
if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
    $result['avatar'] = ['value' => '', 'is_valid' => false, 'errors' => 'JPEG or PNG required'];
}
```

---

## 3. PHP - Migrating to Respect/Validation 2.x

> **Default engine note**
> The default validation engine is `NativeAdapter` (dependency-free).
> If you are using the default configuration, you have no Respect/Validation dependency to remove.
> This section applies only to projects that explicitly use `RespectAdapter`.

> **Version assumption**
> The PHP side targets Respect/Validation **2.x**.
> If this plugin's dependency is bumped to 3.x in the future, a separate migration guide will be added.

### 3.1 Type mapping quick reference

| SV API | Respect/Validation 2.x | Note |
|:--|:--|:--|
| `SV::string()` | `v::stringType()` | |
| `SV::integer()` | `v::intType()` | |
| `SV::number()` | `v::numericVal()` | |
| `SV::boolean()` | `v::boolType()` | |
| `SV::string()->length($min, $max)` | `v::stringType()->length($min, $max)` | |
| `SV::string()->min($n)` | `v::stringType()->length($n, null)` | |
| `SV::string()->max($n)` | `v::stringType()->length(null, $n)` | |
| `SV::integer()->min($n)` | `v::intType()->min($n)` | |
| `SV::integer()->max($n)` | `v::intType()->max($n)` | |
| `SV::number()->min($n)` | `v::numericVal()->min($n)` | |
| `SV::number()->max($n)` | `v::numericVal()->max($n)` | |
| `SV::string()->email()` | `v::email()` | |
| `SV::string()->url()` | `v::url()` | |
| `SV::string()->pattern('...')` | `v::regex('/pattern/u')` | |
| `SV::string()->date()` | `v::date('Y-m-d')` | |
| `SV::string()->dateTime()` | `v::dateTime()` | |
| `SV::string()->time()` | `v::time('H:i:s')` | |
| `SV::string()->uuid()` | `v::uuid()` | |
| `SV::string()->ipv4()` | `v::ip('*', FILTER_FLAG_IPV4)` | |
| `SV::string()->ipv6()` | `v::ip('*', FILTER_FLAG_IPV6)` | |
| `SV::string()->slug()` | `v::slug()` | |
| `SV::string()->domain()` | `v::domain()` | |
| `SV::enum(['a', 'b'])` | `v::in(['a', 'b'])` | |
| `SV::array(SV::string())` | `v::each(v::stringType())` | |
| `RespectRules::rule(v::...)` | Use `v::...` directly | |
| `RespectRules::postalCode('JP')` | `v::postalCode('JP')` | |
| `RespectRules::creditCard()` | `v::creditCard()` | |
| `RespectRules::iban()` | `v::iban()` | |

### 3.2 Using Validator directly

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

> **Array fields**  
> `SV::array(SV::string())->minItems(1)->maxItems(5)` has no direct equivalent rule in Respect/Validation 2.x. Use `v::each(v::stringType())` to validate individual elements, and check the element count manually in PHP.
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

### 3.3 Handling optional / nullable

| SV | Respect/Validation 2.x |
|:--|:--|
| `SV::string()->optional()` | `v::optional(v::stringType())` |
| `SV::string()->nullable()` | `v::nullable(v::stringType())` |
| `SV::string()->optional()->nullable()` | `v::nullable(v::optional(v::stringType()))` |

`v::optional()` skips `null` and empty string `''`. `v::nullable()` skips `null` but validates `''`.

### 3.4 Replacing when() conditional branching

`when()` has no native equivalent rule in Respect/Validation 2.x. **Implement it using PHP conditional logic after validation.**

#### Pattern A - Simple equality condition

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

// 1. Run the standard validation
$result = validateFields($schema, $_POST); // loop function from the previous section

// 2. Apply conditional logic as a post-processing step
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

#### Pattern B - Inequality condition (SV::notEqual)

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

#### Pattern C - Numeric comparison condition (SV::greaterThanOrEqual, etc.)

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

| SV condition | PHP equivalent |
|:--|:--|
| `SV::equal('x')` or direct scalar value | `=== 'x'` |
| `SV::notEqual('x')` | `!== 'x'` |
| `SV::greaterThanOrEqual(18)` | `>= 18` (after numeric cast) |
| `SV::lessThanOrEqual(100)` | `<= 100` (after numeric cast) |
| `SV::greaterThan(0)` | `> 0` (after numeric cast) |
| `SV::lessThan(18)` | `< 18` (after numeric cast) |

#### Pattern D - Cross-field reference (SV::field)

```php
// Before
SV::object([
    'password'         => SV::string()->length(8, 255),
    'password_confirm' => SV::string()->optional(),
])->when('password', SV::notEqual(SV::field('password_confirm')), ['password_confirm']);
// Note: This pattern means "make password_confirm required when password and password_confirm differ",
//       but in practice it is more natural to simply verify that the two fields match.

// After - replace with a match check
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

#### Consolidating multiple conditions

When multiple `when()` conditions are chained, it is recommended to extract them together as a post-processing function.

```php
function applyConditionals(array &$result, array $data): void
{
    // Condition 1: company_name is required when type === 'company'
    if (($data['type'] ?? null) === 'company') {
        requireField($result, $data, 'company_name');
    }

    // Condition 2: guardian is required when age < 18
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

### 3.5 Validation result format changes

SV's `getResult()` returns the following format:

```php
[
    'field_name' => [
        'value'    => mixed,   // raw input value
        'is_valid' => bool,
        'errors'   => string|null,  // Respect/Validation full message or null
    ],
    // ...
]
```

When using Respect/Validation directly, this format does not exist. If you are passing the result to a frontend or downstream process, update those call sites accordingly.

---

## 4. Client - Defining validation rules independently

When using schemable-validator, the PHP schema is the single source of truth.
`SchemaBuilder::toJsonSchema()` produces a JSON Schema document, and the client package (or a Zod/Valibot adapter) derives validation rules from it at runtime.

After removal, this automatic synchronisation no longer exists.
Each validation rule must be maintained in both PHP and client code.

The practical consequence: when a backend developer adds a field or changes a constraint (e.g. raises `minLength` from 1 to 3), the client schema must be updated in a separate commit.
Without a shared JSON Schema, there is no mechanism to detect this drift automatically.

To manage this:

- Keep server and client validation definitions in the same PR when changing constraints.
- Consider a CI step that compares a JSON fixture (exported from your PHP tests) with the client schema to catch divergence early.

The sections below show how to rewrite each client-side rule using standalone libraries.

---

## 5. Client - Migrating TypeScript/JavaScript to Zod

Migrating to Zod removes the JSON Schema adapter layer so you define Zod schemas directly.

```bash
npm install zod
# or
pnpm add zod
```

### 5.1 Type mapping quick reference

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
| `SV::string()->optional()` | Make the field itself `.optional()` |
| `SV::string()->nullable()` | `z.string().nullable()` |

### 5.2 validateObject → Zod safeParse

**Before:**

```typescript
import { validateObject, isAllValid } from '@uuki/schemable-validator-client'
import type { ObjectSchema } from '@uuki/schemable-validator-client'

// Fetch the schema generated by SchemaBuilder::toJsonSchema() and use it
const schema: ObjectSchema = await fetchSchema('/api/schema')

const result = validateObject(formData, schema)
if (isAllValid(result)) {
  // Submit
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
  // Submit (result.data is typed)
} else {
  // Retrieve error details from result.error.issues
  const errors = Object.fromEntries(
    result.error.issues.map((issue) => [issue.path.join('.'), issue.message])
  )
}
```

### 5.3 Replacing when() with Zod

#### Pattern A - Using `superRefine`

This is the most flexible approach and handles multiple conditions and cross-field references.

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

#### Pattern B - Using `z.discriminatedUnion`

When type branching based on a field value is clear-cut, this provides stronger type safety.

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

#### Pattern C - Numeric comparison condition

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

#### Pattern D - Cross-field reference (password confirmation, etc.)

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

#### Chaining multiple conditions

`superRefine` allows you to write multiple conditions within a single schema.

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

### 5.4 Other libraries

Reference information for cases where you prefer a library other than Zod.

#### Valibot

Provides the same declarative schema approach as Zod but with a lighter footprint. A good choice when bundle size is a priority.

```typescript
import * as v from 'valibot'

const schema = v.object({
  name:  v.pipe(v.string(), v.minLength(1), v.maxLength(100)),
  email: v.pipe(v.string(), v.email()),
  type:  v.picklist(['personal', 'company']),
})

// when() alternative - implement conditional requirements with check
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

Natively supports `when()`, allowing a coding style close to SV's conditional structure.

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

## Note: toJsonSchema() / JSON Schema handling

The JSON Schema output by `SchemaBuilder::toJsonSchema()` conforms to the standard draft 2020-12 (`x-when` is an extension key). You can continue to use it alongside a different JSON Schema validator, but if you switch to a library like Zod, the JSON Schema is no longer needed and can be removed.

---

## Related documentation

- [Respect/Validation 2.x - List of Rules](https://respect-validation.readthedocs.io/en/2.4/08-list-of-rules-by-category/)
- [Respect/Validation 2.x → 3.x Migration Guide](https://github.com/Respect/Validation/blob/main/docs/migration-guide.md) *(refer when migrating to 3.x)*
- [Zod documentation](https://zod.dev)
- [Valibot documentation](https://valibot.dev)
