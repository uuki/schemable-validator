# Feature Guide

## Validator

The core class for validating input values against a field schema. Text, file, and reCAPTCHA validation can be combined via method chaining.

### 1. Instantiation

::: code-group

```php [Core]
use SchemableValidator\Validator;

$validator = new Validator($schema);
```

```php [WordPress]
$validator = schv_validator($schema);
```

:::

### 2. Schema Definition

A schema is defined as an associative array of `field name => validation rule`. The recommended approach is the SchemaBuilder API (`SV::string()`, `SV::object()`, etc.), which works without any external dependency.

Taking the `name` field as an example:

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name' => SV::string()->min(2)->max(50),
]);
```

`SV::string()->min(2)->max(50)` means "must be a string and between 2 and 50 characters". Multiple constraints can be chained together.

| Value | Result | Reason |
|:--|:--|:--|
| `'Alice'` | ✓ passes | String, 5 characters |
| `'A'` | ✗ rejected | 1 character (below the minimum of 2) |
| `''` | ✗ rejected | 0 characters |
| `123` | ✗ rejected | Not a string |

Example defining common fields:

```php
$schema = SV::object([
  'name'  => SV::string()->min(2)->max(50),
  'email' => SV::string()->email(),
  'tel'   => SV::string()->pattern('^(0\d{9,10}|0\d{1,4}-\d{1,4}-\d{3,4})$')->optional(),
  'type'  => SV::enum(['general', 'support', 'sales', 'other']),
  'body'  => SV::string()->min(10),
]);
```

::: tip Raw Respect schemas
If you install the optional `respect/validation` package, you can also pass raw Respect rules directly — e.g. `'name' => v::stringType()->length(2, 50)`. Wrap them with `SV::respect(v::...)` when using SchemaBuilder.
:::

Passing and failing examples:

```php
// ✓ all pass
$data = [
  'name'  => 'Alice',
  'email' => 'alice@example.com',
  'tel'   => '090-1234-5678',
  'type'  => 'general',
  'body'  => 'Thank you for your inquiry. I look forward to hearing from you.',
];

// ✗ all rejected
$data = [
  'name'  => 'A',            // 1 character
  'email' => 'not-an-email', // invalid format
  'tel'   => '12345',        // pattern mismatch
  'type'  => 'unknown',      // value not allowed
  'body'  => 'short',        // fewer than 10 characters
];
```

### 3. Field Validation

Pass the input array to `validate()` and retrieve the result with `getResult()`. Input values are automatically sanitized (`strip_tags` + `htmlspecialchars`).

```php
$result = $validator->validate($_POST)->getResult();
```

Result structure when `name` passes and `email` fails with the schema above:

```php
[
  'name' => [
    'value'    => 'Alice',
    'is_valid' => true,
    'errors'   => null,
  ],
  'email' => [
    'value'    => 'not-an-email',
    'is_valid' => false,
    'errors'   => 'must be a valid email',
  ],
  'tel' => [
    'value'    => '090-1234-5678',
    'is_valid' => true,
    'errors'   => null,
  ],
  // remaining fields follow the same structure
]
```

Check whether all fields are valid:

```php
$all_valid = array_reduce($result, fn($carry, $field) => $carry && $field['is_valid'], true);
```

### 4. Advanced Validation

Use `validateFiles()` for file upload validation.

```php
// pass $_FILES directly
$result = $validator->validateFiles($_FILES)->getResult();

// pass an array other than $_FILES
$result = $validator->validateFiles($data, ['native_files' => false])->getResult();
```

Use `SV::file()` (backed by `NativeFileValidator`) to restrict allowed MIME types:

```php
use SchemableValidator\SV;

$schema = SV::object([
  'file' => SV::file(['image/jpeg', 'image/png']),
]);
```

::: info Legacy
The `FileExtension` rule class still works but is considered legacy. Prefer `SV::file()` for new code.
:::

::: tip
For advanced usage such as defining custom rules for address validation, see [Custom Validation](/custom-validation). You can also use `SV::custom(callable)` as a dependency-free escape hatch for one-off rules.

Note: `creditCard` and `postalCode` rules are **@deprecated** and have been moved to `Drivers\Respect\RespectRules`.
:::

### Method Chaining

```php
$result = $validator
  ->validate($_POST)
  ->validateFiles($_FILES)
  ->validateReCaptcha()
  ->getResult();
```

---

## SchemaBuilder

A declarative schema definition API starting from `SV::object()`. From the same definition, you can both **output JSON Schema (draft 2020-12)** and **generate a Validator instance**. Unlike defining a Validator directly, this enables integration with client-side validation libraries and OpenAPI tools.

### Field Definition

Pass an associative array of field names and field schemas to `SV::object()`.

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name'    => SV::string()->min(2)->max(50),
  'email'   => SV::string()->email(),
  'tel'     => SV::string()->pattern('^0\d{9,10}$')->optional(),
  'type'    => SV::enum(['general', 'support', 'other']),
  'age'     => SV::integer()->min(0)->max(150)->optional(),
  'website' => SV::string()->url()->nullable()->optional(),
  'avatar'  => SV::file(['image/jpeg', 'image/png'])->optional(),
]);
```

Main field types:

| Method | JSON Schema `type` | Main constraint methods |
|:--|:--|:--|
| `SV::string()` | `"string"` | `.min()` `.max()` `.email()` `.url()` `.pattern()` |
| `SV::integer()` | `"integer"` | `.min()` `.max()` |
| `SV::number()` | `"number"` | `.min()` `.max()` |
| `SV::boolean()` | `"boolean"` | - |
| `SV::enum(['a', 'b'])` | `"string"` + `enum` | - |
| `SV::file(['image/jpeg'])` | - *Cannot be converted to JSON Schema* | - |
| `SV::respect(v::...)` | - *Cannot be converted to JSON Schema* | - |

Modifiers:

| Modifier | Effect |
|:--|:--|
| `.optional()` | Excludes the field from the `required` array |
| `.nullable()` | Extends the type to allow `null` |

### JSON Schema Output

```php
echo $schema->toJson();          // JSON string
$array = $schema->toJsonSchema(); // array
```

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "properties": {
    "name":  { "type": "string", "minLength": 2, "maxLength": 50 },
    "email": { "type": "string", "format": "email" },
    "type":  { "type": "string", "enum": ["general", "support", "other"] }
  },
  "required": ["name", "email", "type"]
}
```

::: info Sharing rules with the frontend
Use SchemaBuilder when you want to share validation rules with the frontend. By passing the JSON Schema output from `toJson()` to the frontend via a REST API, you can manage the PHP-side rule definitions as the single source of truth, preventing duplicate definitions and implementation drift.
:::

### Converting to Validator

Generate a `Validator` instance with `toValidator()`. You can chain `validateFiles()` and `validateReCaptcha()` directly onto it.

```php
$result = $schema->toValidator()
  ->validate($_POST)
  ->validateFiles($_FILES)
  ->getResult();
```

::: tip
For details on conditional required fields (`.when()`) and WordPress REST endpoint registration (`schv_register_schema()`), see [SchemaBuilder](/schema-builder).
:::

---

## Error Messages

This section explains how to access error messages included in the validation result and how to customize them to match your locale.

### Retrieving Error Messages

Each field in `getResult()` contains an `errors` key. The value is `null` when validation passes and an error message string when it fails. The default is the English message from the DefaultMessages canonical catalog (engine-neutral).

```php
$result = $validator->validate($_POST)->getResult();

foreach ($result as $field => $state) {
  if (!$state['is_valid']) {
    echo $field . ': ' . $state['errors'];
  }
}
```

When multiple rules fail, errors are returned as a string joined by `"\n"`.

```php
// when name fails both string and minLength
$result['name']['errors'];
// → 'must be a string
//    must be at least 2 characters'
```

### Inline Rule Override

Use the `errorMessage()` method on any field to override the error message with `{var}` interpolation.

```php
$schema = SV::object([
  'email' => SV::string()->email()->errorMessage('Please enter a valid email address'),
  'name'  => SV::string()->min(2)->max(50)
               ->errorMessage('{field} must be between {min} and {max} characters'),
]);
```

Available placeholders depend on the constraint: `{field}`, `{min}`, `{max}`, `{pattern}`, etc.

::: info
`errorMessage()` applies a single message to the whole field. Use MessageDict if you need per-rule control.
:::

### Internationalization

`MessageDict` lets you define error messages as a dictionary on a per-field, per-rule basis. It also supports a Japanese preset and site-wide application via WordPress filters.

#### Step 1: Prepare dictionary files

`MessageDict` accepts two kinds of input: **locale defaults** and **field definitions**. Keeping each as a separate PHP file makes them easier to manage.

**Locale defaults** — keyed by rule ID, defines messages shared across all fields regardless of which field they apply to.

```php
// messages/ja.php — keyed by engine-neutral vocabulary (see I18n/DefaultMessages.php)
return [
  'string'    => 'Please enter a string',
  'minLength' => 'Must be at least {min} characters',
  'maxLength' => 'Must be at most {max} characters',
  'email'     => 'Please enter a valid email address',
  'required'  => 'This field is required',
  'integer'   => 'Please enter an integer',
  'number'    => 'Please enter a number',
  'uri'       => 'Please enter a valid URL',
  'pattern'   => 'The input format is incorrect',
  'enum'      => 'Please choose from the available options',
];
```

**Field definitions** — keyed by field name, overrides messages specific to each field. There are three ways to write them.

```php
// messages/fields.php
return [
  // Pattern 1: field-wide (same message regardless of which rule fails)
  'email' => 'Please enter your email address correctly',

  // Pattern 2: field × rule specific (individual message per rule)
  'name' => [
    'length' => 'Name must be between 2 and 50 characters',
  ],

  // Pattern 3: multiple rules specified individually
  'body' => [
    'required' => 'Please enter the message body',
    'pattern'  => 'Message body must be at least 10 characters',
  ],
];
```

::: info Priority
When multiple definitions exist for the same field and rule, they are resolved in the following order:

1. Field × rule specific — `['name' => ['length' => '...']]`
2. Field-wide — `['email' => '...']`
3. Locale default — contents of `messages/ja.php`
4. DefaultMessages canonical catalog (engine-neutral)
:::

#### Step 2: Loading

Pass the prepared files to `MessageDict` to create an instance.

```php
use SchemableValidator\I18n\MessageDict;

// use the built-in Japanese preset as-is
$dict = MessageDict::ja();

// use a custom dictionary file
$dict = new MessageDict(
  require __DIR__ . '/messages/fields.php', // field definitions
  require __DIR__ . '/messages/ja.php'       // locale defaults
);

// combine the Japanese preset with field definitions
$dict = MessageDict::ja(
  require __DIR__ . '/messages/fields.php'
);
```

`MessageDict::en()` returns the DefaultMessages canonical catalog (engine-neutral) as-is.

#### Step 3: Passing to Validator

::: code-group

```php [Core]
use SchemableValidator\Validator;
use SchemableValidator\I18n\MessageDict;

$dict = MessageDict::ja(require __DIR__ . '/messages/fields.php');

$validator = new Validator($schema, [], [], $dict);
```

```php [WordPress]
use SchemableValidator\I18n\MessageDict;

$dict = MessageDict::ja(require __DIR__ . '/messages/fields.php');

$validator = schv_validator($schema, [], $dict);
```

:::

When passing via SchemaBuilder:

```php
use SchemableValidator\SV;
use SchemableValidator\I18n\MessageDict;

$dict = MessageDict::ja(require __DIR__ . '/messages/fields.php');

$result = SV::object([
  'name'  => SV::string()->min(2)->max(50),
  'email' => SV::string()->email(),
])->withMessages($dict)
  ->toValidator()
  ->validate($_POST)
  ->getResult();
```

#### Site-wide default (WordPress)

Overriding the dictionary via the `schv_message_dict` filter causes it to be automatically applied whenever `schv_validator()` is called.

```php
add_filter('schv_message_dict', function (MessageDict $dict): MessageDict {
  return $dict->merge(require __DIR__ . '/messages/fields.php');
});

// omitting $dict automatically applies the result of the schv_message_dict filter
$validator = schv_validator($schema);
```

---

## Security

This section explains security-related features and best practices for forms.

### CSRF Token

A token generation and verification feature built into `Validator`. It prevents request forgery by storing a form-scoped token in the session and verifying it on submission.

```php
// on form display: generate a token and store it in the session
$token = $validator->createToken();

// on form submission: verify the token
$is_valid = $validator->checkToken($_POST['schv_csrf_token'] ?? '');
```

Embed it as a hidden field in the form:

```html
<input type="hidden" name="schv_csrf_token" value="<?php echo esc_attr($token); ?>">
```

### Best Practices

| Item | Description |
|:--|:--|
| Use CSRF tokens | Enable `createToken()` / `checkToken()` on all POST forms |
| Use reCAPTCHA | Combine `validateReCaptcha()` on public forms to prevent spam and automated submissions |
| Escape output | Although `value` in `getResult()` has already been processed with `strip_tags` + `htmlspecialchars`, escape it again when outputting to HTML |

---

## Session Management

The `FormController` feature stores validated data in the session, maintaining state across multi-page forms such as input → confirm → complete flows.

::: code-group

```php [Core]
use SchemableValidator\Controllers\FormController;

$form = new FormController();
```

```php [WordPress]
$form = schv_form();
```

:::

| Method | Description |
|:--|:--|
| `save(array $data): void` | Saves the return value of `getResult()` to the session |
| `get(): ?array` | Retrieves saved data. Returns `null` if nothing has been saved |
| `clear(): void` | Removes data from the session |

::: code-group

```php [Core]
use SchemableValidator\Controllers\FormController;

// Step 1: validate → save → redirect
$result = $validator->validate($_POST)->getResult();
$all_valid = array_reduce($result, fn($c, $i) => $c && $i['is_valid'], true);

if ($all_valid) {
  (new FormController())->save($result);
  header('Location: /confirm/');
  exit;
}

// Step 2: retrieve on the confirmation screen
$data = (new FormController())->get();

// Step 3: clear after completion
(new FormController())->clear();
```

```php [WordPress]
// Step 1: validate → save → redirect
$result = $validator->validate($_POST)->getResult();
$all_valid = array_reduce($result, fn($c, $i) => $c && $i['is_valid'], true);

if ($all_valid) {
  schv_form()->save($result);
  wp_redirect('/confirm/');
  exit;
}

// Step 2: retrieve on the confirmation screen
$data = schv_form()->get();

// Step 3: clear after completion
schv_form()->clear();
```

:::

---

## Template

Inserts validated data into a template string with placeholders. Useful for generating email body content.

Example template files (`templates/user.txt`, `templates/admin.txt`):

```
Thank you for your inquiry.

Name: {name}
Email: {email}

Message:
{body}

We will get back to you shortly.
```

```
A new inquiry has been received.

Name: {name}
Reply to: {email}

Message:
{body}
```

Instantiate by including the template files:

::: code-group

```php [Core]
use SchemableValidator\Template;

$template = new Template([
  'aliases'   => [
    'name'  => 'name',   // {name} in template → $data['name']['value']
    'email' => 'email',
    'body'  => 'body',
  ],
  'templates' => [
    'user'  => file_get_contents(__DIR__ . '/templates/user.txt'),
    'admin' => file_get_contents(__DIR__ . '/templates/admin.txt'),
  ],
]);
```

```php [WordPress]
$template = schv_template([
  'aliases'   => ['name' => 'name', 'email' => 'email', 'body' => 'body'],
  'templates' => [
    'user'  => 'SCHV_REPLY_FORMAT_FOR_user',   // WP option name
    'admin' => 'SCHV_REPLY_FORMAT_FOR_admin',
  ],
]);
```

:::

```php
$user_mail  = $template->get('user');
$admin_mail = $template->get('admin');
$all        = $template->getAll();
```

::: info
`aliases` is a mapping of `template key => field name in $data`. Use this when form field names differ from template placeholder names.
:::

::: warning WordPress
The value of `templates` is interpreted as a WP option name and the body is retrieved with `get_option()`. Do not pass template strings directly.
:::

---

## Other Features

### reCAPTCHA v3

Send `$_POST['recaptcha_token']` from the frontend.

::: code-group

```php [Core]
$validator = new Validator($schema, [
  'recaptcha_secret'      => 'YOUR_SECRET_KEY',
  'recaptcha_valid_score' => 0.5,
]);
```

```php [WordPress]
$validator = schv_validator($schema, [
  'recaptcha_secret'      => 'YOUR_SECRET_KEY',
  'recaptcha_valid_score' => 0.5,
]);
```

:::

```php
$result = $validator
  ->validate($_POST)
  ->validateReCaptcha([
    'action' => 'contact', // optional: also verify that the action name matches
  ])
  ->getResult();
```
