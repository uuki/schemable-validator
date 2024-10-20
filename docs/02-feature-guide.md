# Feature Guide

## Namespace import (In Refinement)

```php
use SchemableValidator\Validator;
```

**As of now**

```php
require_once __DIR__ . '/vendor/uuki/schemable-validator/src/index.php';
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

## Template

### Default

```php
use SchemableValidator\Template;

$template = new Template([
  'name' => 'name',
  'email' => 'email',
  'body' => 'body',
], [
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
]);
```

### With WordPress

Templates can be defined from the admin panel.

```php
use SchemableValidator\Template;

$template = new Template([
  'name' => 'name',
  'email' => 'email',
  'body' => 'body',
]);
```

## Data store

```php
use SchemableValidator\FormController;

$form_controller = new FormController();
$form_data = $form_controller->get(); // Validated results.

$format_any = $template->get(/* Template name */);
```