<?php
namespace SchemableValidator;

require SV_ROOT_DIR . "/vendor/autoload.php";
use Respect\Validation\Factory;
use Respect\Validation\Validator as v;

class Validator {
  /**
   * @var array<string, v> $schema
   */
  private array $schema;

  /**
   * @param array<string, v> $schema
   */
  function __construct(array $schema = []) {
    $this->schema = $schema;
    $this->state = [];

    Factory::setDefaultInstance(
      (new Factory())
        ->withRuleNamespace('SchemableValidator\\Rules')
        ->withExceptionNamespace('SchemableValidator\\Exceptions')
    );
  }

  function validate(array $data) {
    foreach($this->schema as $name => $validator) {
      $value = isset($data[$name]) ? $this->sanitize($data[$name]) : null;
      $newState = $this->assertByValidator($value, $validator);

      $this->state[$name] = $newState;
    }

    return $this->state;
  }

  function validateFiles(array $data, array $options = []) {
    $options = array_merge([
      'native_files' => true,
    ], $options);
    $normalized_data = $data;

    if ($options['native_files']) {
      foreach ($data as $name => $files) {
        $normalized_data[$name] = $this->normalizeFile($files);
      }
    }

    foreach ($normalized_data as $name => $files) {
      if (!isset($this->schema[$name])) {
        continue;
      }
      $validator = $this->schema[$name];
      $this->state[$name] = [];

      foreach ($files as $index => $file_data) {
        $newState = $this->assertByValidator($file_data, $validator);
        array_push($this->state[$name], $newState);
      }
    }
    return $this->state;
  }

  private function assertByValidator($data, $validator) {
    $newState = [
      'value' => null,
      'errors' => null,
      'is_valid' => false,
    ];

    $newState['value'] = $data;

    try {
      $validator->assert($data);
    } catch(\Respect\Validation\Exceptions\ValidationException $e) {
      $newState['errors'] = $e->getFullMessage();
    }

    if (!isset($newState['errors'])) {
      $newState['is_valid'] = true;
    }
    return $newState;
  }

  /**
   * Unify $_FILES data structures for multiple and single files since they are different.
   */
  private function normalizeFile(array $file) {
    $result = [];

    if (isset($file['name']) && is_array($file['name'])) {
      foreach ($file['name'] as $index => $name) {
        $result[] = [
          'name' => $file['name'][$index] ?? '',
          'type' => $file['type'][$index] ?? '',
          'tmp_name' => $file['tmp_name'][$index] ?? '',
          'error' => $file['error'][$index] ?? UPLOAD_ERR_NO_FILE,
          'size' => $file['size'][$index] ?? 0,
        ];
      }
    } else {
      $result[] = [
        'name' => $file['name'] ?? '',
        'type' => $file['type'] ?? '',
        'tmp_name' => $file['tmp_name'] ?? '',
        'error' => $file['error'] ?? UPLOAD_ERR_NO_FILE,
        'size' => $file['size'] ?? 0,
      ];
    }
    return $result;
  }

  private function sanitize(string $str) {
    $result = '';
    // Remove html tags
    $result = strip_tags($str);
    // Convert special characters to HTML entities
    $result = htmlspecialchars($result, ENT_QUOTES, 'UTF-8');
    // Remove newlines, tabs, and other control characters and whitespace
    $result = trim(preg_replace('/[\r\n\t]/', '', $result));
    return $result;
  }
}