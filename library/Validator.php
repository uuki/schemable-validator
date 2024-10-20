<?php
/**
 * Plugin Name: schemable-validator
 * Plugin URI: None
 * Description: Respect/Validation based, schema-based validation library
 * Version: 0.1.0
 * Author: uuki<uuki.dev@gmail.com>
 */

namespace SchemableValidator;

require_once "constants.php";
require SV_VENDOR_DIR . "/autoload.php";

use Respect\Validation\Factory;
use Respect\Validation\Validator as v;

/**
 * Class Validator
 *
 * Provide methods related to validation according to the defined schema.
 */
final class Validator {
  /**
   * @var array<string, v> $schema
   */
  private array $schema;

  /**
   * Validator constructor.
   *
   * @param array<string, v> $schema An associative array where keys are field names and values are Respect\Validation\Validator instances. Validation rules can be found here https://github.com/Respect/Validation/blob/2.2/docs/list-of-rules.md
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

  /**
   * Validate the provided $_POST against the defined schema.
   *
   * @param array<string, mixed> $data The data to be validated, where keys correspond to schema field names.
   *
   * @return array<string, array<string, mixed>> Array of validation results.
   */
  function validate(array $data) {
    foreach($this->schema as $name => $validator) {
      $value = isset($data[$name]) ? $this->sanitize($data[$name]) : null;
      $newState = $this->assertByValidator($value, $validator);

      $this->state[$name] = $newState;
    }

    return $this->state;
  }

  /**
   * Validates file uploads against the defined schema.
   *
   * @param array<string, array{
   *     name: string,
   *     type: string,
   *     tmp_name: string,
   *     error: int,
   *     size: int
   * }> $data The file data to be validated, Or, type $_FILES.
   *
   * @param array<string, mixed> $options An optional array of options for file validation. Default is ['native_files' => true]. When passing data from an array other than $_FILES, use false.
   *
   * @return array<string, array<string, mixed>> Array of validation results.
   */
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