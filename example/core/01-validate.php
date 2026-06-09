<?php
/**
 * Example: Basic validation
 * Run: php example/core/01-validate.php
 */
require_once __DIR__ . '/../../vendor/autoload.php';

use Respect\Validation\Validator as v;
use SchemableValidator\Validator;

$schema = [
  'name'  => v::stringType()->length(1, 50),
  'email' => v::email(),
  'body'  => v::stringType()->length(1, 1000),
];

// --- Valid ---
$result = (new Validator($schema))
  ->validate(['name' => 'Alice', 'email' => 'alice@example.com', 'body' => 'Hello!'])
  ->getResult();

echo "=== Valid ===\n";
foreach ($result as $field => $state) {
  echo "{$field}: " . ($state['is_valid'] ? 'OK' : 'NG') . "\n";
}

// --- Invalid ---
$result = (new Validator($schema))
  ->validate(['name' => '', 'email' => 'not-an-email', 'body' => ''])
  ->getResult();

echo "\n=== Invalid ===\n";
foreach ($result as $field => $state) {
  echo "{$field}: " . ($state['is_valid'] ? 'OK' : "NG — {$state['errors']}") . "\n";
}
