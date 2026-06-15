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
use SchemableValidator\I18n\MessageDict;
use SchemableValidator\Schema\FieldRef;
use SchemableValidator\Schema\WhenExpr;
use SchemableValidator\Validation\Adapters\RespectAdapter;
use SchemableValidator\Validation\BackendAdapter;

/**
 * Class Validator
 *
 * Provide methods related to validation according to the defined schema.
 */
final class Validator {

  /** @var array<string, v> */
  private array $schema;

  /** @var array<array{field: string, value: mixed, require: string[]}> */
  private array $conditionals;

  /** @var array<string, mixed> */
  private array $options;

  /** @var array<string, mixed> */
  private array $state;

  private ?MessageDict $dict;

  private BackendAdapter $adapter;

  /**
   * Validator constructor.
   *
   * @param array<string, v> $schema An associative array where keys are field names and values are Respect\Validation\Validator instances.
   */
  function __construct(array $schema = [], array $options = [], array $conditionals = [], ?MessageDict $dict = null, ?BackendAdapter $adapter = null) {
    $this->schema = $schema;
    $this->conditionals = $conditionals;
    $this->dict = $dict;
    $this->adapter = $adapter ?? new RespectAdapter();
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
      $value = $data[$name] ?? null;
      $this->state['result'][$name] = $this->assert($value, $validator, $name);
    }

    // Apply conditional requirements
    foreach ($this->conditionals as $cond) {
      /** @var WhenExpr $expr */
      $expr        = $cond['expr'];
      $triggerRaw  = $data[$cond['field']] ?? null;
      $triggerStr  = is_array($triggerRaw)
        ? implode(',', array_map('strval', $triggerRaw))
        : (string) ($triggerRaw ?? '');

      // Resolve the right-hand operand (literal or field reference)
      $operand    = $expr->operand;
      $operandStr = ($operand instanceof FieldRef)
        ? (string) ($data[$operand->name] ?? '')
        : (string) $operand;

      $triggerNum = self::toFloat($triggerStr);
      $operandNum = self::toFloat($operandStr);
      if ($expr->op === '!==') {
        $matches = $triggerStr !== $operandStr;
      } elseif ($expr->op === '>=') {
        $matches = $triggerNum >= $operandNum;
      } elseif ($expr->op === '<=') {
        $matches = $triggerNum <= $operandNum;
      } elseif ($expr->op === '>') {
        $matches = $triggerNum > $operandNum;
      } elseif ($expr->op === '<') {
        $matches = $triggerNum < $operandNum;
      } else {
        $matches = $triggerStr === $operandStr; // '==='
      }

      if (!$matches) {
        continue;
      }
      foreach ($cond['require'] as $requiredField) {
        $val = $data[$requiredField] ?? null;
        $isEmpty = $val === null || $val === '' || $val === [];
        if ($isEmpty) {
          $state = $this->createState();
          $state['value']  = $val;
          $state['errors'] = $this->dict
            ? $this->dict->resolve($requiredField, 'required', "{$requiredField} is required")
            : "{$requiredField} is required";
          $this->state['result'][$requiredField] = $state;
        }
      }
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
        $this->state['result'][$name][] = $this->assert($file_data, $validator, $name);
      }
    }

    return $this;
  }

  /**
   * @param array<string, mixed> $options
   * @return static
   * @throws \RuntimeException if recaptcha_secret is not configured
   * @throws \InvalidArgumentException if recaptcha_provider is not an allowed endpoint
   */
  public function validateReCaptcha(array $options = []): self {
    // A02-2: reject unconfigured secret rather than silently failing
    if ($this->options['recaptcha_secret'] === '') {
      throw new \RuntimeException(
        'recaptcha_secret must be configured before calling validateReCaptcha()'
      );
    }

    // A10-1: allow-list the provider URL to Google reCAPTCHA endpoints only
    $provider        = $this->options['recaptcha_provider'];
    $allowedPrefixes = [
      'https://www.google.com/recaptcha/',
      'https://www.recaptcha.net/recaptcha/',
    ];
    $allowed = false;
    foreach ($allowedPrefixes as $prefix) {
      if (strncmp($provider, $prefix, strlen($prefix)) === 0) {
        $allowed = true;
        break;
      }
    }
    if (!$allowed) {
      throw new \InvalidArgumentException(
        'recaptcha_provider must begin with https://www.google.com/recaptcha/ ' .
        'or https://www.recaptcha.net/recaptcha/'
      );
    }

    $curl    = new CurlController();
    $options = array_merge(['action' => null], $options);

    $newState = $this->createState();
    $params   = [
      'secret'   => $this->options['recaptcha_secret'],
      'response' => $this->state['recaptcha_token'],
    ];

    try {
      $result           = $curl->post($provider, $params);
      $recaptcha_result = json_decode($result['response']);

      // A05-1: guard against malformed responses before accessing properties
      if (!isset($recaptcha_result->success)) {
        throw new \RuntimeException('Malformed reCAPTCHA response');
      }

      $newState['is_valid'] = (bool) $recaptcha_result->success;

      if ($newState['is_valid'] && isset($recaptcha_result->score)) {
        $newState['value']    = $recaptcha_result->score;
        $newState['is_valid'] = $recaptcha_result->score >= $this->options['recaptcha_valid_score'];
      }

      if ($options['action'] && isset($recaptcha_result->action)) {
        $newState['is_valid'] = $options['action'] === $recaptcha_result->action;
      }
    } catch(\Exception $e) {
      // A05-1: do not expose internal error details (endpoint URL, network info) to callers
      error_log('schemable-validator: reCAPTCHA verification failed: ' . $e->getMessage());
      $newState['errors'] = 'reCAPTCHA verification failed';
    }

    $this->state['result']['recaptcha'] = $newState;

    return $this;
  }

  public function getResult(): array {
    return $this->state['result'];
  }

  /**
   * Generate a CSRF token scoped to a specific form and store it with a 1-hour expiry.
   *
   * @param string $form Unique identifier for the form (e.g. 'contact', 'login').
   */
  public function createToken(string $form = 'default'): string {
    $new_token = bin2hex(random_bytes(32));
    $this->startSession();

    if (!isset($_SESSION['schv_csrf_tokens']) || !is_array($_SESSION['schv_csrf_tokens'])) {
      $_SESSION['schv_csrf_tokens'] = [];
    }
    $_SESSION['schv_csrf_tokens'][$form] = [
      'token' => $new_token,
      'exp'   => time() + 3600,
    ];

    $this->state['token'] = $new_token;
    return $new_token;
  }

  /**
   * Verify a CSRF token for the given form scope.
   * Returns false if the token is missing, expired, or does not match.
   *
   * @param string $form Must match the $form used in createToken().
   */
  public function checkToken(string $token, string $form = 'default'): bool {
    $this->startSession();
    $stored = $_SESSION['schv_csrf_tokens'][$form] ?? null;

    if (!is_array($stored) || !isset($stored['token'], $stored['exp'])) {
      return false;
    }
    if (time() > $stored['exp']) {
      unset($_SESSION['schv_csrf_tokens'][$form]);
      return false;
    }
    return hash_equals($stored['token'], $token);
  }

  private static bool $sessionStarted = false;

  private function startSession(): void {
    if (!self::$sessionStarted && session_status() !== PHP_SESSION_ACTIVE) {
      // A02-3: enforce secure session cookie attributes when we start the session ourselves
      session_set_cookie_params([
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
      ]);
      session_start();
      self::$sessionStarted = true;
    }
  }

  /**
   * Strict string-to-float conversion that rejects hex literals ("0x…").
   * PHP's (float) cast already ignores the "x" suffix and returns 0.0 for
   * "0x10", but this makes the intent explicit and guards against future
   * PHP behaviour changes that could silently diverge from the TS client.
   */
  private static function toFloat(string $str): float {
    if (is_numeric($str) && !preg_match('/^[+-]?0x/i', $str)) {
      return (float) $str;
    }
    return 0.0;
  }

  /**
   * Delegates exception->message extraction to the configured adapter.
   * RespectAdapter isolates all Respect exception internals; non-Respect
   * adapters fall back to the exception's own id/message.
   *
   * @return array<string, string> ruleId => defaultMessage
   */
  private function extractRuleMessages(
    \Respect\Validation\Exceptions\ValidationException $e
  ): array {
    return $this->adapter instanceof RespectAdapter
      ? RespectAdapter::extractRuleMessages($e)
      : [$e->getId() => $e->getMessage()];
  }

  private function createState(): array {
    return [
      'value' => null,
      'errors' => null,
      'is_valid' => false,
    ];
  }

  private function assert($data, $validator, string $field = ''): array {
    $newState = $this->createState();
    $newState['value'] = $data;

    try {
      $validator->assert($data);
    } catch(\Respect\Validation\Exceptions\ValidationException $e) {
      $ruleMessages = $this->extractRuleMessages($e);
      $resolved = [];
      foreach ($ruleMessages as $ruleId => $defaultMsg) {
        $resolved[] = ($this->dict !== null && $field !== '')
          ? $this->dict->resolve($field, $ruleId, $defaultMsg)
          : $defaultMsg;
      }
      $newState['errors'] = implode("\n", $resolved);
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
