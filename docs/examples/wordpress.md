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
