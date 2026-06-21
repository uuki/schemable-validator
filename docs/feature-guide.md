# Feature Guide

This page covers the primary runtime features: the `Validator` class, error messages, security (CSRF, CAPTCHA), session management, and the `Template` helper.
For the schema definition API, see [SchemaBuilder](./schema-builder.md).
For localisation, see [MessageDict](./message-dict.md).

## Validator

`Validator` runs field validation against a schema. Text, file, and CAPTCHA checks can be chained in any combination.

### 1. Instantiation

::: code-group

```php [Core]
use SchemableValidator\Orchestration\Validator;

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
If you install the optional `respect/validation` package, you can also pass raw Respect rules directly — e.g. `'name' => v::stringType()->length(2, 50)`. Wrap them with `RespectRules::rule(v::...)` when using SchemaBuilder.
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

For `creditCard` and `postalCode` rules, use `RespectRules::creditCard()` and `RespectRules::postalCode()` from `Adapters\Respect\RespectRules`.
:::

### Method Chaining

```php
$result = $validator
  ->validate($_POST)
  ->validateFiles($_FILES)
  ->validateCaptcha()
  ->getResult();
```

---

## SchemaBuilder

`SchemaBuilder` is the recommended way to define a schema.
The same definition produces both a server-side `Validator` (via `toValidator()`) and a JSON Schema export for any JavaScript client (via `toJson()`).

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name'  => SV::string()->min(2)->max(50),
  'email' => SV::string()->email(),
  'type'  => SV::enum(['general', 'support', 'other']),
]);

// Server-side validation
$result = $schema->toValidator()->validate($_POST)->validateFiles($_FILES)->getResult();

// Export to JSON Schema for the frontend
echo $schema->toJson();
```

### Hiding fields from client output

Fields marked `.serverOnly()` are validated server-side as normal but excluded from the JSON Schema output sent to clients.
They do not appear in `properties`, `required`, or `x-unmapped-fields`.

```php
$schema = SV::object([
  'email'       => SV::string()->email(),
  'risk_score'  => SV::integer()->min(0)->max(100)->serverOnly(),
]);

echo $schema->toJson();
// risk_score is absent — invisible to clients

$schema->toValidator()->validate($data)->getResult();
// validates both email and risk_score
```

::: tip
For the full field type reference, `.nullable()`, `.optional()`, `.serverOnly()`, conditional required (`.when()`), `x-unmapped-fields`, and WordPress REST endpoint registration, see [SchemaBuilder](./schema-builder.md).
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

`MessageDict` defines messages per field and per rule, and supports a built-in Japanese preset.

```php
use SchemableValidator\SV;
use SchemableValidator\I18n\MessageDict;

$result = SV::object([
  'name'  => SV::string()->min(2)->max(50),
  'email' => SV::string()->email(),
])->withMessages(MessageDict::ja([
  'email' => 'メールアドレスが正しくありません',
]))->toValidator()->validate($_POST)->getResult();
```

::: tip
For the full API — locale presets, per-rule keys, placeholder interpolation (`{min}`, `{max}`), the message resolution priority table, WordPress filters, and migration from Respect rule ids — see [MessageDict](./message-dict.md).
:::

---

## Security

This section covers CSRF token management, CAPTCHA verification, and form security best practices.

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

### CAPTCHA

Inject a `CaptchaDriver` via `toValidator()`, then call `validateCaptcha()`.
Three providers are built in: `ReCaptchaV3Driver`, `HCaptchaDriver`, and `TurnstileDriver`.

```php
use SchemableValidator\Adapters\Captcha\ReCaptchaV3Driver;

$validator = $schema->toValidator([], [
  'captchaDriver' => new ReCaptchaV3Driver('YOUR_SECRET'),
]);

$result = $validator
  ->validate($_POST)           // reads g-recaptcha-response / h-captcha-response / cf-turnstile-response
  ->validateCaptcha([
    'action' => 'contact',     // optional action check (reCAPTCHA v3 only)
  ])
  ->getResult();
```

The result is written under `$result['captcha']`:

```json
{ "value": 0.9, "is_valid": true, "errors": null }
```

To switch providers, replace the driver:

```php
use SchemableValidator\Adapters\Captcha\HCaptchaDriver;
use SchemableValidator\Adapters\Captcha\TurnstileDriver;

// hCaptcha
'captchaDriver' => new HCaptchaDriver('YOUR_SECRET')

// Cloudflare Turnstile
'captchaDriver' => new TurnstileDriver('YOUR_SECRET')
```

In tests and local development, use `NullCaptchaDriver`, which bypasses the network entirely:

```php
use SchemableValidator\Adapters\Captcha\NullCaptchaDriver;

'captchaDriver' => new NullCaptchaDriver() // always passes; pass false to simulate rejection
```

For the full driver reference including security properties and score threshold, see [Backend Adapters](./backend-adapters.md#injecting-a-captcha-driver).

### Best Practices

| Item | Description |
|:--|:--|
| Use CSRF tokens | Enable `createToken()` / `checkToken()` on all POST forms |
| Use CAPTCHA | Inject a `CaptchaDriver` and call `validateCaptcha()` on public forms to prevent spam |
| Escape output | Although `value` in `getResult()` has already been processed with `strip_tags` + `htmlspecialchars`, escape it again when outputting to HTML |

---

## Session Management

The `FormController` feature stores validated data in the session, maintaining state across multi-page forms such as input → confirm → complete flows.

::: code-group

```php [Core]
use SchemableValidator\Infrastructure\FormController;

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
use SchemableValidator\Infrastructure\FormController;

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

::: warning Session affinity required
`FormController` stores data in PHP's native session (`$_SESSION`).
In a load-balanced environment without sticky sessions, a user's request may be routed to a different server between steps, causing `get()` to return `null` on the confirmation page.

For example, a two-server configuration without session affinity:

```
User → Server A  (Step 1: save() writes to Server A's session file)
User → Server B  (Step 2: get() reads Server B's session file → null)
```

To avoid this, configure a shared session backend:

```php
// php.ini or runtime configuration
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis-host:6379');
```

Alternatively, replace `FormController` with a token-based approach that passes encrypted form data through hidden fields and does not depend on server-side session state.
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
use SchemableValidator\Orchestration\Template;

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

