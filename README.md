
# Schemable Validator

The Schemable Validator was developed to streamline the validation processing involved in form submissions.

## ✨ Features

- 🍨 **Vanilla PHP Based**: Designed to be independent of specific systems such as CMS platforms.
- ✅ **Validation**: Built on flexible and powerful validation processing using Respect/Validation, with sanitization features.
- 📂 **Data Store**: Equipped with a controller that allows for easy storage of validated data for reuse across different pages and processes.
- ⚙️ **Customizability**: Supports replaceable reply templates through aliases.

## 📦 Install

```shell
composer require uuki/schemable-validator:0.x@dev
```

## 🐣 Usage

### Step 1.

Include plugin.

```php
require_once __DIR__ . '/path/to/vendor/uuki/schemable-validator/src/index.php';
```

### Step 2.

Create schema.

```php
use Respect\Validation\Validator as v;

$schema = [
  'type' => v::notEmpty()
    ->setTemplate('Please select an item')
    ->in(['option1', 'option2', 'option3']),
  'name' => v::stringType()->length(1, 50),
  'email' => v::email(),
  'phone' => v::phone()->length(10, 15),
  'url' => v::url(),
  'address' => v::stringType()->length(1, 255),
  'body' => v::stringType()->length(1, 1000),
  'usage' => v::notEmpty()->in(['for_business', 'for_personal']),
  'docs' => v::key('error', v::equals(UPLOAD_ERR_OK))
    ->key('name', v::oneOf(
      v::extension('jpg'),
      v::extension('png'),
    )),
  'agreement' => v::trueVal()
];
```

### Step 3.

Create validator instance.

```php
use SchemableValidator\Validator;

$validator = new Validator($schema);
```

### Step 4.

Validation at any time.

```php
$result = $validator->validate($_POST);
```

### More documentations

[docs](docs)

## Dependencies
- [Respect/Validation](https://packagist.org/packages/respect/validation#2.2.4)

## Futures
- Some options
  - reCAPTCHA
  - i18n
