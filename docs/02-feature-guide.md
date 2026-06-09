# Feature Guide

## Namespace import

```php
use SchemableValidator\Validator;
```

## Validation schema

Respect Validation based.

```php
use Respect\Validation\Validator as v;

$schema = [
  'name' => v::stringType()->length(1, 50)
];
```

[More rules](https://github.com/Respect/Validation/tree/main/docs/rules)

## Validation

### Simple validation flow.

```php
$validator = new Validator($schema);
$result = $validator->validate($_POST)->getResult();
```

### Files

```php
$result = $validator
  ->validateFiles($_FILES, [
    'native_files' => true // default
  ])
  ->getResult();
```

### reCAPTCHA

```php
// $_POST['recaptcha_token'] = 'token_here';

$result = $validator
  ->validate($_POST)
  ->validateReCaptcha([
    'action' => 'action_name', // optional
  ])
  ->getResult();
```

### Support chain methods.

```php
$validator = new Validator($schema);
$result = $validator
  ->validate($_POST)
  ->validateFiles($_FILES)
  ->validateReCaptcha()
  ->getResult();
```

## Security

```php
$csrf_token = $validator->createToken();
$is_verified = $validator->checkToken($csrf_token);
```

## Template

### Default

```php
use SchemableValidator\Template;

// $options = [$aliases, $templates];
$template = new Template([
  [
    'contact_name' => 'name',
    'contact_email' => 'email',
    'contact_body' => 'body',
  ],
  [
    'for_user': <<<'EOD'
  Hello, {name}.
  We have received your inquiry with the following details.
  ---
  {body}
  EOD,
    'for_admin': <<<'EOD'
  An inquiry has been received from {name}.
  ---
  {body}
  EOD,
  ]
]);

$user_reply_format = $template->get('for_user');
$admin_reply_format = $template->get('for_admin');
$all_format = $template->getAll();
```

## With WordPress

Install the plugin and make the template configurable from the admin page.

```php
require_once get_template_directory() . '/vendor/autoload.php';

use SchemableValidator\Interfaces\Wordpress\Plugin;
$plugin = new Plugin([
  'wp_format_user' => [
    'title' => 'Reply format（User）',
    'description' => 'Use {name}, {email}, {body} as placeholders.',
  ],
  'wp_format_admin' => [
    'title' => 'Reply format（Admin）',
    'description' => 'Use {name}, {email}, {body} as placeholders.',
  ],
]);

$option_field_keys = $plugin->keysAll();
```

### How to get the template in this case.

No need to define a template for the second argument.

```php
use SchemableValidator\Template;

// Pass an array with $key = field name and $value = string to replace in the body.
$template = new Template([
  [
    'contact_name' => 'name',
    'contact_email' => 'email',
    'contact_body' => 'body',
  ],
  $option_field_keys,
]);

$user_reply_format = $template->get('wp_format_user');
$admin_reply_format = $template->get('wp_format_admin');
$all_format = $template->getAll();
```

## Data store

```php
use SchemableValidator\FormController;

$form_controller = new FormController();
$form_data = $form_controller->get(); // Validated results.

$format_any = $template->get(/* Template name */);
```