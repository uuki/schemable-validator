# MessageDict - Multilingual Error Messages

`MessageDict` is a value object that lets you define validation error messages as a dictionary, keyed by field and rule.

---

## Basic Usage

```php
use SchemableValidator\I18n\MessageDict;

// Japanese preset (applies default messages in bulk)
$dict = MessageDict::ja();

// English (pass-through to Respect defaults)
$dict = MessageDict::en();
```

### Adding Custom Definitions

```php
$dict = MessageDict::ja([
  // Override the entire field (same message regardless of rule)
  'email' => 'The email address is invalid',

  // Override per rule
  'name' => [
    'length' => 'Name must be between 2 and 50 characters',
  ],
]);
```

---

## Passing to a Validator

### Direct Constructor

```php
use SchemableValidator\Validator;
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

`resolve(field, ruleId, fallback)` resolves messages in the following order.

| Priority | Condition | Message Used |
|:--|:--|:--|
| 1 | `$definitions[$field][$ruleId]` exists | Field + rule specific |
| 2 | `$definitions[$field]` is a string | Field-level shorthand |
| 3 | `$defaults[$ruleId]` exists | Locale preset |
| 4 | None of the above match | Respect default (English) |

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

Default messages applied by `MessageDict::ja()`.

| Rule ID | Default Message |
|:--|:--|
| `stringType` | Please enter a string |
| `length` | Length is out of range |
| `email` | Please enter a valid email address |
| `notEmpty` | This field is required |
| `notOptional` | This field is required |
| `integer` / `intType` | Please enter an integer |
| `numeric` | Please enter a number |
| `url` | Please enter a valid URL |
| `regex` | The input format is invalid |
| `in` / `anyOf` | Please select from the available options |
| `required` | This field is required (for conditional required) |
