<?php
/**
 * Example: reCAPTCHA v3 validation
 * Run: php example/core/03-recaptcha.php
 *
 * Replace RECAPTCHA_SECRET with your actual secret key.
 * The frontend must send the token via POST['recaptcha_token'].
 */
require_once __DIR__ . '/../../vendor/autoload.php';

use Respect\Validation\Validator as v;
use SchemableValidator\Validator;

$validator = new Validator(
  ['name' => v::stringType()->notEmpty()],
  [
    'recaptcha_secret'      => 'RECAPTCHA_SECRET',
    'recaptcha_valid_score' => 0.5,
  ]
);

// Simulate: $_POST would contain 'recaptcha_token' from the frontend widget.
$post = [
  'name'            => 'Alice',
  'recaptcha_token' => 'token-from-frontend',
];

$result = $validator
  ->validate($post)
  ->validateReCaptcha(['action' => 'contact'])
  ->getResult();

echo "name:      " . ($result['name']['is_valid'] ? 'OK' : 'NG') . "\n";
echo "recaptcha: " . ($result['recaptcha']['is_valid'] ? 'OK' : 'NG') . "\n";

if ($result['recaptcha']['errors']) {
  echo "error: " . $result['recaptcha']['errors']['message'] . "\n";
}
