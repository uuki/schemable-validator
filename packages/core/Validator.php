<?php
/**
 * Plugin Name: schemable-validator
 * Plugin URI: None
 * Description: Respect/Validation based, schema-based validation library
 * Version: 0.1.0
 * Author: uuki<uuki.dev@gmail.com>
 */

namespace SchemableValidator;

require_once __DIR__ . '/constants.php';
require_once SV_VENDOR_DIR . '/autoload.php';

use Respect\Validation\Factory;
use Respect\Validation\Validator as v;
use SchemableValidator\Controllers\CurlController;

/**
 * Class Validator
 *
 * Provide methods related to validation according to the defined schema.
 */
final class Validator {
  use Helpers\Security;

  /**
   * @var array<string, v> $schema
   */
  private array $schema;

  /** @var array<string, mixed> */
  private array $options;

  /** @var array<string, mixed> */
  private array $state;

  /**
   * Validator constructor.
   *
   * @param array<string, v> $schema An associative array where keys are field names and values are Respect\Validation\Validator instances. Validation rules can be found here https://github.com/Respect/Validation/blob/2.2/docs/list-of-rules.md
   */
  function __construct(array $schema = [], array $options = []) {
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
   * @return static
   */
  public function validate(array $data): self {
    if (!empty($data['recaptcha_token'])) {
      $this->state['recaptcha_token'] = $data['recaptcha_token'];
    }

    foreach($this->schema as $name => $validator) {
      $value = isset($data[$name]) ? $this->sanitize($data[$name]) : null;
      $this->state['result'][$name] = $this->assert($value, $validator);
    }

    return $this;
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
   * @return static
   */
  public function validateFiles(array $data, array $options = []): self {
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

      foreach ($files as $file_data) {
        $this->state['result'][$name][] = $this->assert($file_data, $validator);
      }
    }

    return $this;
  }

  /**
   * @param array<string, mixed> $options
   * @return static
   */
  public function validateReCaptcha(array $options = []): self {
    $curl = new CurlController();
    $options = array_merge([
      'action' => null,
    ], $options);

    $newState = $this->createState();
    $params = [
      'secret' => $this->options['recaptcha_secret'],
      'response' => $this->state['recaptcha_token'],
    ];

    $recaptcha_result = null;

    try {
      $result = $curl->post($this->options['recaptcha_provider'], $params);
      $recaptcha_result = json_decode($result['response']);

      $newState['value'] = $recaptcha_result->score;
      $newState['is_valid'] = $recaptcha_result->success &&
        $recaptcha_result->score >= $this->options['recaptcha_valid_score'];

      if ($options['action']) {
        $newState['is_valid'] = $options['action'] === $recaptcha_result->action;
      }
    } catch(\Exception $e) {
      $newState['errors'] = [
        'message' => $e->getMessage(),
        'result' => [
          'success' => $recaptcha_result->success ?? null,
          'action' => $recaptcha_result->action ?? null,
        ],
      ];
    }

    $this->state['result']['recaptcha'] = $newState;

    return $this;
  }

  public function getResult(): array {
    return $this->state['result'];
  }

  public function createToken(): string {
    $new_token = bin2hex(random_bytes(32));
    $this->startSession();
    $_SESSION['schv_csrf_token'] = $new_token;
    $this->state['token'] = $new_token;

    return $new_token;
  }

  public function checkToken(string $token): bool {
    $this->startSession();
    return isset($_SESSION['schv_csrf_token']) && hash_equals($_SESSION['schv_csrf_token'], $token);
  }

  private function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
  }

  private function createState(): array {
    return [
      'value' => null,
      'errors' => null,
      'is_valid' => false,
    ];
  }

  private function assert($data, $validator): array {
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

  private function normalizeFile(array $file): array {
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
}
