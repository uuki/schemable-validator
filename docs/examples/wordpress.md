# Examples - WordPress

Implementation examples for an environment with the plugin activated as a WordPress plugin. Uses `schv_*` helper functions.

> Source code: [`packages/example/wordpress/`](https://github.com/uuki/schemable-validator/tree/v0.9.1/packages/example/wordpress)

---

## 1. Basic Validation

Create a validator with `schv_validator()` and handle form submissions via the `template_redirect` hook.

<<< ../../packages/example/wordpress/01-validate.php

[View on GitHub](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/wordpress/01-validate.php)

---

## 2. File Upload Validation

Validate `$_FILES` with `validateFiles()` and restrict the allowed MIME types.

<<< ../../packages/example/wordpress/02-validate-files.php

[View on GitHub](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/wordpress/02-validate-files.php)

---

## 3. CSRF Token Protection

Embed a token in a hidden field with `createToken()` and verify it on submission using `checkToken()`.

<<< ../../packages/example/wordpress/03-csrf.php

[View on GitHub](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/wordpress/03-csrf.php)

---

## 4. Mail Template Rendering

Use `schv_template()` to inject validated data into a WP options template.

<<< ../../packages/example/wordpress/04-template.php

[View on GitHub](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/wordpress/04-template.php)

---

## 5. Multi-page Form (Input → Confirm → Complete)

Use `schv_form()` to persist data in the session and implement a form spanning three pages.

<<< ../../packages/example/wordpress/05-multipage-form.php

[View on GitHub](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/wordpress/05-multipage-form.php)

---

## 6. Merging a Schema Editor definition with code

The Schema Editor (admin UI) defines primitive fields such as string, email, and enum.
`mergeJsonSchema()` combines them with code-defined logic that the GUI cannot express: file uploads, conditional requirements, custom validators, and driver injection.

```php
use SchemableValidator\SV;
use SchemableValidator\Adapters\Captcha\ReCaptchaV3Driver;
use SchemableValidator\Adapters\Native\NativeImageDriver;

// 1. Load the schema created via Schema Editor (slug: "contact")
$gui = schv_stored_schema('contact')->toJsonSchema();

// 2. Add code-side fields and conditions, then merge
$schema = SV::object([
  'avatar'       => SV::file(['image/jpeg', 'image/png'], ['maxWidth' => 4096]),
  'company_name' => SV::string()->min(1)->max(200)->optional(),
])->mergeJsonSchema($gui)
  ->when('type', SV::equal('company'), ['company_name']);

// 3. Validate with drivers
$result = $schema
  ->toValidator([
    'imageDriver'   => new NativeImageDriver(),
    'captchaDriver' => new ReCaptchaV3Driver('YOUR_SECRET'),
  ])
  ->validate($_POST)
  ->validateFiles($_FILES)
  ->validateCaptcha()
  ->getResult();
```

The GUI-defined fields (`name`, `email`, `type`) and the code-defined fields (`avatar`, `company_name`) are validated together.
If the same field name appears in both, the code definition takes precedence.

To expose the merged schema as a REST endpoint for client-side consumption:

```php
schv_register_schema('/contact', schv_stored_schema('contact'));
```
