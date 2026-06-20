<?php
/**
 * Example: CAPTCHA validation (reCAPTCHA v3)
 * Run: php example/core/03-recaptcha.php
 *
 * Replace YOUR_SECRET with your actual secret key.
 * The frontend must send the token via POST (e.g. 'g-recaptcha-response').
 */
require_once __DIR__ . '/../../vendor/autoload.php';

use SchemableValidator\SV;
use SchemableValidator\Validation\Captcha\ReCaptchaV3Driver;

$schema = SV::object([
  'name' => SV::string()->min(1),
]);

$validator = $schema->toValidator([
  'captchaDriver' => new ReCaptchaV3Driver('YOUR_SECRET'),
]);

// Simulate: $_POST would contain 'g-recaptcha-response' from the frontend widget.
$post = [
  'name'                 => 'Alice',
  'g-recaptcha-response' => 'token-from-frontend',
];

$result = $validator
  ->validate($post)
  ->validateCaptcha(['action' => 'contact'])
  ->getResult();

echo "name:    " . ($result['name']['is_valid'] ? 'OK' : 'NG') . "\n";
echo "captcha: " . ($result['captcha']['is_valid'] ? 'OK' : 'NG') . "\n";

if ($result['captcha']['errors']) {
  echo "error: " . $result['captcha']['errors'] . "\n";
}
