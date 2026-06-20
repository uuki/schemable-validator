# Examples - Core

Implementation examples using the framework-agnostic core library.

> Source code: [`packages/example/core/`](https://github.com/uuki/schemable-validator/tree/v0.9.1/packages/example/core)

---

## 1. Basic Validation

Define a field schema and validate input values with `Validator`.

<<< ../../packages/example/core/01-validate.php

[View on GitHub](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/core/01-validate.php)

---

## 2. File Upload Validation

Use `validateFiles()` to validate the extension and error code of uploaded files.

<<< ../../packages/example/core/02-validate-files.php

[View on GitHub](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/core/02-validate-files.php)

---

## 3. CAPTCHA Validation (reCAPTCHA v3)

Inject a `CaptchaDriver` and call `validateCaptcha()` to verify the score threshold and action name.

<<< ../../packages/example/core/03-recaptcha.php

[View on GitHub](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/core/03-recaptcha.php)

---

## 4. CSRF Token

Generate a token with `createToken()` and verify it against form submissions using `checkToken()`.

<<< ../../packages/example/core/04-csrf-token.php

[View on GitHub](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/core/04-csrf-token.php)

---

## 5. Template Rendering

Use the `Template` class to inject validated session data into an email body.

<<< ../../packages/example/core/05-template.php

[View on GitHub](https://github.com/uuki/schemable-validator/blob/v0.9.1/packages/example/core/05-template.php)
