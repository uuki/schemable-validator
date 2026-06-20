# MessageDict

`MessageDict` defines validation error messages as a dictionary keyed by field name and **neutral rule id**.
Pass it to any `Validator` or `SchemaBuilder` instance to localise error text or override individual field messages.

> **Breaking change (rule-id rekeying).**

> **Breaking change (rule-id rekeying).** Message keys are now an engine-neutral
> vocabulary (`minLength`, `maxLength`, `minimum`, `maximum`, `email`, `uri`,
> `pattern`, `enum`, `string`/`integer`/`number`/`boolean`, …) instead of
> Respect's internal ids (`stringType`, `length`, `intType`, `numeric`, `in`, …).
> See [Migration](#migration-from-respect-rule-ids) below for the full mapping.
> This keeps messages identical across the Respect, Opis and native backends.

---

## Basic Usage

```php
use SchemableValidator\I18n\MessageDict;

// Japanese preset (applies default messages in bulk)
$dict = MessageDict::ja();

// English: defaults are supplied by the canonical catalog (DefaultMessages),
// so en() needs no entries of its own.
$dict = MessageDict::en();
```

### Adding Custom Definitions

```php
$dict = MessageDict::ja([
  // Override the entire field (same message regardless of rule)
  'email' => 'The email address is invalid',

  // Override per rule (neutral rule id keys)
  'name' => [
    'minLength' => 'Name must be at least 2 characters',
    'maxLength' => 'Name must be no more than 50 characters',
  ],
]);
```

### Placeholder Interpolation

Templates may contain `{var}` (and the ICU-style `{var, type}`, whose type is
ignored) placeholders, filled from the failing rule's values — `{min}`/`{max}`
for length/range, `{values}` for `enum`. The same substitution runs on the FE.

```php
$dict = MessageDict::ja([
  'name' => ['minLength' => 'Must be at least {min} characters'],
]);
// → "Must be at least 2 characters"
```

---

## Passing to a Validator

### Direct Constructor

```php
use SchemableValidator\Orchestration\Validator;
use SchemableValidator\I18n\MessageDict;

$validator = new Validator($schema, [], [], MessageDict::ja());
```

### Via SchemaBuilder

```php
use SchemableValidator\SV;
use SchemableValidator\I18n\MessageDict;

$schema = SV::object([
  'name'  => SV::string()->min(2)->max(50),
  'email' => SV::string()->email(),
])->withMessages(MessageDict::ja([
  'email' => 'The email address is invalid',
]));

$result = $schema->toValidator()->validate($_POST)->getResult();
```

---

## WordPress Helpers

### Per-call Override

Pass as the third argument to `schv_validator()`.

```php
use SchemableValidator\I18n\MessageDict;

$validator = schv_validator($schema, [], MessageDict::ja([
  'email' => 'The email address is invalid',
]));
```

### Site-wide Default (Filter)

Overriding the dictionary via the `schv_message_dict` filter applies it automatically whenever `schv_validator()` is called.

```php
add_filter('schv_message_dict', function (MessageDict $dict): MessageDict {
  return $dict->merge([
    'email' => 'Please enter a valid email address',
    'name'  => ['length' => 'Name must be between 2 and 50 characters'],
  ]);
});

// The filter is applied to subsequent schv_validator() calls
$validator = schv_validator($schema);
```

> A per-call override (third argument) takes priority over the filter.

---

## Message Resolution Priority

When a field fails a rule, the backend resolves the message in this order
(highest first). `MessageDict` (1–3) is always consulted first via
`resolve(field, neutralRuleId, fallback, vars)`; the schema's inline
`errorMessage` and the canonical catalog are passed in as the fallback.

| Priority | Source | Keyed by |
|:--|:--|:--|
| 1 | `MessageDict` field + rule (`$definitions[$field][$rule]`) | neutral rule id |
| 2 | `MessageDict` field-wide string (`$definitions[$field]`) | — |
| 3 | `MessageDict` locale preset (`$defaults[$rule]`, e.g. `ja()`) | neutral rule id |
| 4 | Schema inline `errorMessage[keyword]` | JSON Schema keyword |
| 5 | Canonical catalog (`DefaultMessages`) | neutral rule id |
| 6 | Engine message (Respect/Opis) — only for rules with no neutral mapping | — |

> A configured `MessageDict` (incl. a locale preset) therefore outranks a
> schema-level inline `errorMessage`: the operator's dictionary is the final say.

---

## merge() - Immutable Composition

`merge()` returns a new `MessageDict` without modifying the original instance.

```php
$base  = MessageDict::ja();
$extra = $base->merge(['tel' => 'The phone number format is invalid']);

// $base is unchanged
```

### Rule Definitions Within a Field Are Preserved

When both sides have an array value for a field, they are merged one level deep. Existing rule definitions are not overwritten.

```php
$base = new MessageDict([
  'name' => [
    'length' => 'Length is out of range',
    'email'  => 'Invalid email',
  ],
]);

$next = $base->merge([
  'name' => ['length' => 'Name must be between 2 and 50 characters'],
]);

// 'length' takes the new value; 'email' retains the original
// $next->resolve('name', 'length', '') → 'Name must be between 2 and 50 characters'
// $next->resolve('name', 'email',  '') → 'Invalid email'
```

When a field's type changes (string to array, or array to string), the `merge()` side takes priority.

---

## Japanese Preset Reference

Default messages applied by `MessageDict::ja()`, keyed by the neutral vocabulary.

| Neutral rule id | Default Message (ja) |
|:--|:--|
| `string` | 文字列で入力してください |
| `integer` | 整数で入力してください |
| `number` | 数値で入力してください |
| `boolean` | 真偽値で入力してください |
| `minLength` | 最低{min}文字で入力してください |
| `maxLength` | 最大{max}文字まで入力できます |
| `minimum` | {min}以上で入力してください |
| `maximum` | {max}以下で入力してください |
| `email` | 有効なメールアドレスを入力してください |
| `uri` | 有効なURLを入力してください |
| `date` / `date-time` / `time` | 有効な日付/日時/時刻を入力してください |
| `uuid` | 有効なUUIDを入力してください |
| `ipv4` / `ipv6` | 有効なIPv4/IPv6アドレスを入力してください |
| `hostname` | 有効なホスト名を入力してください |
| `pattern` | 入力形式が正しくありません |
| `enum` | 選択肢から選んでください |
| `required` | 必須項目です（条件付き必須時） |

---

## Migration from Respect rule ids

Keys changed from Respect's internal rule ids to the neutral vocabulary. Update
any custom `MessageDict` definitions and `schv_message_dict` filters:

| Old key (Respect id) | New key (neutral) |
|:--|:--|
| `stringType` | `string` |
| `intType` / `integer` | `integer` |
| `numeric` | `number` |
| (n/a) | `boolean` |
| `length` | `minLength` and/or `maxLength` (split by which bound failed) |
| (n/a) | `minimum` / `maximum` |
| `email` | `email` (unchanged) |
| `url` | `uri` |
| `regex` | `pattern` |
| `in` / `anyOf` | `enum` |
| `notEmpty` / `notOptional` | `required` |

`email` is the only key that stays the same. The biggest change is `length`,
which now splits into `minLength`/`maxLength` so the two bounds carry distinct
messages and `{min}`/`{max}` placeholders.
