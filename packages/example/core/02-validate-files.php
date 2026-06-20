<?php
/**
 * Example: File upload validation
 * Run: php example/core/02-validate-files.php
 */
require_once __DIR__ . '/../../vendor/autoload.php';

use Respect\Validation\Validator as v;
use SchemableValidator\Orchestration\Validator;

$schema = [
  'attachment' => v::key('error', v::equals(UPLOAD_ERR_OK))
    ->key('name', v::oneOf(v::extension('jpg'), v::extension('png'))),
];

// Simulate a valid uploaded file (native_files: false skips PHP's multi-upload normalization)
$files = [
  'attachment' => [[
    'name'     => 'photo.jpg',
    'type'     => 'image/jpeg',
    'tmp_name' => '',
    'error'    => UPLOAD_ERR_OK,
    'size'     => 204800,
  ]],
];

$result = (new Validator($schema))
  ->validateFiles($files, ['native_files' => false])
  ->getResult();

echo "=== File validation ===\n";
foreach ($result['attachment'] as $i => $state) {
  echo "file[{$i}]: " . ($state['is_valid'] ? 'OK' : "NG — {$state['errors']}") . "\n";
}

// Invalid: upload error
$files['attachment'][0]['error'] = UPLOAD_ERR_INI_SIZE;
$result = (new Validator($schema))
  ->validateFiles($files, ['native_files' => false])
  ->getResult();

echo "\n=== File with upload error ===\n";
echo "file[0]: " . ($result['attachment'][0]['is_valid'] ? 'OK' : "NG — {$result['attachment'][0]['errors']}") . "\n";
