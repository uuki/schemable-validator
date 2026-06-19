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

use Respect\Validation\Validator as v;
use SchemableValidator\Controllers\CurlController;
use SchemableValidator\I18n\MessageDict;
use SchemableValidator\Validation\Adapters\NativeAdapter;
use SchemableValidator\Validation\Adapters\RespectAdapter;
use SchemableValidator\Validation\BackendAdapter;
use SchemableValidator\Validation\CaptchaDriver;
use SchemableValidator\Validation\FileValidationDriver;
use SchemableValidator\Validation\ImageDriver;
use SchemableValidator\Validation\JsonLogicEval;
use SchemableValidator\Validation\NativeFileValidator;
use SchemableValidator\Validation\Transform;
use SchemableValidator\Validation\RespectExecutableValidator;

/**
 * Class Validator
 *
 * Provide methods related to validation according to the defined schema.
 */
final class Validator {

  /**
   * Respect validator instances executed directly. In legacy mode (constructed
   * with raw `v` objects) this is the full field set. In IR mode (built via
   * SchemaBuilder/fromJsonSchema) this holds ONLY the UnmappableField escape
   * hatches (FileSchema/RawRespectSchema) — mappable fields go through $adapter.
   * @var array<string, v>
   */
  private array $schema;

  /**
   * Mappable-field JSON Schema (IR). When non-null, validate() dispatches
   * field validation through $adapter->compile($jsonSchema); when null, the
   * legacy RespectExecutableValidator path over $schema is used.
   * @var array<string, mixed>|null
   */
  private ?array $jsonSchema;

  /**
   * (B) escape-hatch fields (SV::custom / SV::respect), executed via
   * CustomField::evaluate() — engine-agnostic, no IR representation.
   * @var array<string, \SchemableValidator\Validation\CustomField>
   */
  private array $customFields;

  /** @var array<array{condition: array, require: string[]}> */
  private array $conditionals;

  /** @var array<string, string[]> field → transform list */
  private array $transforms;

  /** @var array<string, array<string, string>> field → (JSON Schema keyword → message template) */
  private array $inlineMessages;

  /** @var array<string, mixed> */
  private array $options;

  /** @var array<string, mixed> */
  private array $state;

  private ?MessageDict $dict;

  private BackendAdapter $adapter;

  /**
   * File-field configs (field => ['accept' => string[]]) for SV::file() fields,
   * dispatched to $fileDriver by validateFiles() — a dependency-free path that
   * replaces the old Respect FileExtension rule.
   * @var array<string, array<string, mixed>>
   */
  private array $fileConfigs;

  private FileValidationDriver $fileDriver;

  /** @var ImageDriver|null Null = image constraints are skipped. */
  private ?ImageDriver $imageDriver;

  /** @var CaptchaDriver|null Null = use legacy validateReCaptcha() path. */
  private ?CaptchaDriver $captchaDriver;

  /**
   * Validator constructor.
   *
   * @param array<string, v> $schema An associative array where keys are field names and values are Respect\Validation\Validator instances.
   */
  function __construct(array $schema = [], array $options = [], array $conditionals = [], ?MessageDict $dict = null, ?BackendAdapter $adapter = null, array $transforms = [], array $inlineMessages = [], ?array $jsonSchema = null, array $fileConfigs = [], ?FileValidationDriver $fileDriver = null, array $customFields = [], ?ImageDriver $imageDriver = null, ?CaptchaDriver $captchaDriver = null) {
    $this->schema = $schema;
    $this->jsonSchema = $jsonSchema;
    $this->customFields = $customFields;
    $this->conditionals = $conditionals;
    $this->transforms = $transforms;
    $this->inlineMessages = $inlineMessages;
    $this->fileConfigs = $fileConfigs;
    $this->fileDriver = $fileDriver ?? new NativeFileValidator();
    $this->imageDriver   = $imageDriver;
    $this->captchaDriver = $captchaDriver;
    $this->dict = $dict;
    // Default engine is the dependency-free NativeAdapter, so respect/validation
    // is optional (composer "suggest"). Pass a RespectAdapter explicitly (or use
    // SV::respect/RespectRules / a raw `v` schema) to opt into Respect.
    $this->adapter = $adapter ?? new NativeAdapter();
    $this->options = array_merge([
      'recaptcha_provider' => 'https://www.google.com/recaptcha/api/siteverify',
      'recaptcha_secret' => '',
      'recaptcha_valid_score' => 0.5,
    ], $options);
    $this->state = [
      'result'          => [],
      'token'           => null,
      'recaptcha_token' => null,
      'captcha_token'   => null,
    ];

    // Respect's factory is configured lazily by the Respect-backed paths
    // (RespectAdapter / RespectExecutableValidator) so the default Native path
    // never loads respect/validation — keeping it a truly optional dependency.
  }

  /**
   * Build a Validator directly from a raw JSON Schema 2020-12 object schema
   * (`properties`/`required`), bypassing SchemaBuilder. Each property is
   * compiled via RespectAdapter, so the resulting Validator behaves the same
   * as one built from SV::object(...)->toValidator().
   *
   * x-when conditionals embedded in $jsonSchema are extracted automatically;
   * explicit $conditionals (JSONLogic format) may be supplied to supplement.
   *
   * @param array<string, mixed> $jsonSchema
   * @param array<string, mixed> $options
   * @param array<array{condition: array, require: string[]}> $conditionals
   */
  public static function fromJsonSchema(array $jsonSchema, array $options = [], array $conditionals = [], ?MessageDict $dict = null, ?BackendAdapter $adapter = null): self {
    $transforms = [];

    foreach ($jsonSchema['properties'] ?? [] as $name => $prop) {
      if (!empty($prop['x-transform'])) {
        $transforms[$name] = $prop['x-transform'];
      }
    }

    // Merge x-when conditionals from the schema itself (JSONLogic format).
    $xWhen = $jsonSchema['x-when'] ?? [];
    if (!empty($xWhen)) {
      $conditionals = array_merge($xWhen, $conditionals);
    }

    // IR mode: field validation is dispatched through $adapter->compile($jsonSchema).
    // Raw JSON Schema input has no UnmappableField escape hatches, so $schema is empty.
    return new self([], $options, $conditionals, $dict, $adapter, $transforms, [], $jsonSchema);
  }

  /**
   * Validate the provided $_POST against the defined schema.
   *
   * @param array<string, mixed> $data The data to be validated, where keys correspond to schema field names.
   *
   * @return static
   */
  public function validate(array $data): self {
    // Capture CAPTCHA token from whichever field name the provider uses.
    foreach (['recaptcha_token', 'g-recaptcha-response', 'h-captcha-response', 'cf-turnstile-response'] as $field) {
      if (!empty($data[$field])) {
        $this->state['recaptcha_token'] = $data[$field]; // legacy key, kept for validateReCaptcha()
        $this->state['captcha_token']   = $data[$field];
        break;
      }
    }

    // Pre-transform field values before validation.
    foreach ($this->transforms as $field => $transforms) {
      if (isset($data[$field]) && is_string($data[$field])) {
        $data[$field] = Transform::apply($data[$field], $transforms);
      }
    }

    // IR mode (SchemaBuilder / fromJsonSchema): dispatch mappable fields through
    // the BackendAdapter, then run any UnmappableField escape hatches (file/raw)
    // via Respect directly. Legacy mode (raw `v` schema): run everything via Respect.
    if ($this->jsonSchema !== null) {
      foreach ($this->adapter->compile($this->jsonSchema, $this->dict)->validate($data) as $name => $fieldState) {
        $this->state['result'][$name] = $fieldState;
      }
      // (B) escape hatches (SV::custom / SV::respect) — engine-agnostic evaluate().
      foreach ($this->customFields as $name => $customField) {
        $this->state['result'][$name] = $customField->evaluate($name, $data[$name] ?? null, $this->dict);
      }
    } else {
      $executable = new RespectExecutableValidator($this->schema, $this->dict, $this->inlineMessages);
      foreach ($executable->validate($data) as $name => $fieldState) {
        $this->state['result'][$name] = $fieldState;
      }
    }

    // Apply conditional requirements (JSONLogic format)
    foreach ($this->conditionals as $cond) {
      if (!JsonLogicEval::apply($cond['condition'], $data)) {
        continue;
      }
      foreach ($cond['require'] as $requiredField) {
        $val     = $data[$requiredField] ?? null;
        $isEmpty = $val === null || $val === '' || $val === [];
        if ($isEmpty) {
          $state           = $this->createState();
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
      // SV::file() fields → dependency-free FileValidationDriver (+ optional ImageDriver).
      if (isset($this->fileConfigs[$name])) {
        $config = $this->fileConfigs[$name];
        $this->state['result'][$name] = [];
        foreach ($files as $file_data) {
          $fileResult = $this->fileDriver->validate($file_data, $config);
          // Run ImageDriver only when the file passed MIME validation, a driver is
          // configured, and the field declares image constraints.
          if ($fileResult['is_valid'] && $this->imageDriver !== null && !empty($config['image'])) {
            $imgResult = $this->imageDriver->validate($file_data, $config['image']);
            if (!$imgResult['is_valid']) {
              $fileResult['is_valid'] = false;
              $fileResult['errors']   = $imgResult['errors'];
            }
          }
          $this->state['result'][$name][] = $fileResult;
        }
        continue;
      }
      // Legacy: a raw Respect validator passed directly as the field's schema.
      if (isset($this->schema[$name])) {
        $validator = $this->schema[$name];
        $this->state['result'][$name] = [];
        foreach ($files as $file_data) {
          $this->state['result'][$name][] = $this->assert($file_data, $validator, $name);
        }
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

  /**
   * Verify a CAPTCHA token using the injected CaptchaDriver.
   *
   * The token is read from the POST field that was captured by validate():
   * 'recaptcha_token', 'g-recaptcha-response', 'h-captcha-response', or
   * 'cf-turnstile-response' — whichever was present first.
   *
   * The result is written to $result['captcha'].
   *
   * @param array<string, mixed> $options Passed through to CaptchaDriver::verify()
   *                                      (e.g. ['action' => 'contact'] for reCAPTCHA v3).
   * @return static
   * @throws \RuntimeException if no CaptchaDriver was configured
   */
  public function validateCaptcha(array $options = []): self {
    if ($this->captchaDriver === null) {
      throw new \RuntimeException(
        'A CaptchaDriver must be set via toValidator([...], [\'captchaDriver\' => ...]) before calling validateCaptcha()'
      );
    }

    $token = (string) ($this->state['captcha_token'] ?? $this->state['recaptcha_token'] ?? '');
    $verifyResult = $this->captchaDriver->verify($token, $options);

    $this->state['result']['captcha'] = [
      'value'    => $verifyResult['score'],
      'is_valid' => $verifyResult['is_valid'],
      'errors'   => $verifyResult['errors'],
    ];

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
