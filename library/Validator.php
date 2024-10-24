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
  function __construct(array $schema = [], $options = []) {
    $this->schema = $schema;

    $this->options = array_merge([
      'recaptcha_provider' => 'https://www.google.com/recaptcha/api/siteverify',
      'recaptcha_secret' => '',
      'recaptcha_valid_score' => 0.5,
    ], $options);

    $this->state = [
      'result' => [],
      'token' => null,
      'recaptcha_token' => null,
    ];

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
    if ($data['recaptcha_token']) {
      $this->state['recaptcha_token'] = $data['recaptcha_token'];
      $this->state['recaptcha_action'] = $data['recaptcha_action'];
    }

    foreach($this->schema as $name => $validator) {
      $value = isset($data[$name]) ? $this->sanitize($data[$name]) : null;
      $newState = $this->assertByValidator($value, $validator);

      $this->state['result'][$name] = $newState;
    }

    return $this->state['result'];
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
      $this->state['result'][$name] = [];

      foreach ($files as $index => $file_data) {
        $newState = $this->assertByValidator($file_data, $validator);
        array_push($this->state['result'][$name], $newState);
      }
    }
    return $this->state['result'];
  }

  private function createState() {
    return [
      'value' => null,
      'errors' => null,
      'is_valid' => false,
    ];
  }

  private function assertByValidator($data, $validator) {
    $newState = $this->createState();

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

  function createToken() {
    $new_token = bin2hex(random_bytes(32));
    $this->state['token'] = $new_token;

    return $new_token;
  }

  function checkToken($token){
    return $token === $this->state['token'];
  }

  function withReCaptcha($result) {
    $newState = $this->createState();
    $c = curl_init();
    $params = [
      'secret' => $this->options['recaptcha_secret'],
      'response' => $this->state['recaptcha_token'],
    ];

    curl_setopt($c, CURLOPT_URL, $this->options['recaptcha_provider']);
    curl_setopt($c, CURLOPT_POST, true);
    curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);

    try {
      $response = curl_exec($c);
      curl_close($c);

      $recaptcha_result = json_decode($response);

      $newState['value'] = $recaptcha_result->score;
      $newState['is_valid'] = $recaptcha_result->success &&
        $recaptcha_result->action === $this->state['recaptcha_action'] &&
        $recaptcha_result->score >= $this->options['recaptcha_valid_score'];
    } catch(\Exception $e) {
      $newState['errors'] = $e;
    }

    $result['recaptcha'] = $newState;

    return $result;
  }
}