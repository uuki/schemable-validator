<?php
/**
 * Plugin Name: schemable-validator
 * Plugin URI: None
 * Description: Respect/Validation based, schema-based validation library
 * Version: 0.1.0
 * Author: uuki<uuki.dev@gmail.com>
 */

namespace SchemableValidator\Orchestration;

require_once __DIR__ . '/../constants.php';
require_once SV_VENDOR_DIR . '/autoload.php';

use SchemableValidator\Adapters\Native\NativeAdapter;
use SchemableValidator\Adapters\Native\NativeFileValidator;
use SchemableValidator\I18n\MessageDict;
use SchemableValidator\Security\CsrfGuard;
use SchemableValidator\Validation\BackendAdapter;
use SchemableValidator\Validation\CaptchaDriver;
use SchemableValidator\Validation\FileValidationDriver;
use SchemableValidator\Validation\ImageDriver;
use SchemableValidator\Validation\JsonLogicEval;
use SchemableValidator\Validation\Transform;

/**
 * Class Validator
 *
 * Provide methods related to validation according to the defined schema.
 */
final class Validator {

  /**
   * Mappable-field JSON Schema (IR). validate() dispatches field validation
   * through $adapter->compile($jsonSchema).
   * @var array<string, mixed>
   */
  private array $jsonSchema;

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

  /** @var CaptchaDriver|null Null = captcha verification is unavailable. */
  private ?CaptchaDriver $captchaDriver;

  /**
   * Validator constructor.
   *
   * @param array<string, mixed> $jsonSchema IR schema (JSON Schema object).
   * @param array<string, mixed> $config     Optional configuration:
   *   'conditionals' => array, 'dict' => ?MessageDict, 'adapter' => ?BackendAdapter,
   *   'transforms' => array, 'fileConfigs' => array, 'fileDriver' => ?FileValidationDriver,
   *   'customFields' => array, 'imageDriver' => ?ImageDriver, 'captchaDriver' => ?CaptchaDriver.
   */
  function __construct(array $jsonSchema = [], array $config = []) {
    $this->jsonSchema    = $jsonSchema;
    $this->conditionals  = $config['conditionals'] ?? [];
    $this->customFields  = $config['customFields'] ?? [];
    $this->transforms    = $config['transforms'] ?? [];
    $this->fileConfigs   = $config['fileConfigs'] ?? [];
    $this->fileDriver    = $config['fileDriver'] ?? new NativeFileValidator();
    $this->imageDriver   = $config['imageDriver'] ?? null;
    $this->captchaDriver = $config['captchaDriver'] ?? null;
    $this->dict          = $config['dict'] ?? null;
    // Default engine is the dependency-free NativeAdapter, so respect/validation
    // is optional (composer "suggest"). Pass a RespectAdapter explicitly (or use
    // SV::respect/RespectRules) to opt into Respect.
    $this->adapter       = $config['adapter'] ?? new NativeAdapter();
    $this->state = [
      'result'        => [],
      'token'         => null,
      'captcha_token' => null,
    ];
  }

  /**
   * Build a Validator directly from a raw JSON Schema 2020-12 object schema
   * (`properties`/`required`), bypassing SchemaBuilder. Each property is
   * compiled via the BackendAdapter, so the resulting Validator behaves the same
   * as one built from SV::object(...)->toValidator().
   *
   * x-when conditionals embedded in $jsonSchema are extracted automatically;
   * explicit $conditionals (JSONLogic format) may be supplied to supplement.
   *
   * @param array<string, mixed> $jsonSchema
   * @param array<array{condition: array, require: string[]}> $conditionals
   */
  public static function fromJsonSchema(array $jsonSchema, array $conditionals = [], ?MessageDict $dict = null, ?BackendAdapter $adapter = null): self {
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

    return new self($jsonSchema, [
      'conditionals' => $conditionals,
      'dict'         => $dict,
      'adapter'      => $adapter,
      'transforms'   => $transforms,
    ]);
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
    foreach (['g-recaptcha-response', 'h-captcha-response', 'cf-turnstile-response', 'recaptcha_token'] as $field) {
      if (!empty($data[$field])) {
        $this->state['captcha_token'] = $data[$field];
        break;
      }
    }

    // Pre-transform field values before validation.
    foreach ($this->transforms as $field => $transforms) {
      if (isset($data[$field]) && is_string($data[$field])) {
        $data[$field] = Transform::apply($data[$field], $transforms);
      }
    }

    // IR mode: dispatch mappable fields through the BackendAdapter.
    foreach ($this->adapter->compile($this->jsonSchema, $this->dict)->validate($data) as $name => $fieldState) {
      $this->state['result'][$name] = $fieldState;
    }
    // (B) escape hatches (SV::custom / SV::respect) — engine-agnostic evaluate().
    foreach ($this->customFields as $name => $customField) {
      $this->state['result'][$name] = $customField->evaluate($name, $data[$name] ?? null, $this->dict);
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
    }

    return $this;
  }

  /**
   * Verify a CAPTCHA token using the injected CaptchaDriver.
   *
   * The token is read from the POST field that was captured by validate():
   * 'g-recaptcha-response', 'h-captcha-response', 'cf-turnstile-response',
   * or 'recaptcha_token' -- whichever was present first.
   *
   * validate() must be called before validateCaptcha() so the token is
   * extracted from POST data. If validateCaptcha() is called without a
   * prior validate(), the token is treated as empty and the result will
   * be is_valid => false with 'CAPTCHA token is missing'.
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
        'A CaptchaDriver must be set via toValidator([\'captchaDriver\' => ...]) before calling validateCaptcha()'
      );
    }

    $token = (string) ($this->state['captcha_token'] ?? '');

    // Short-circuit before any network call when no token was submitted.
    if ($token === '') {
      $this->state['result']['captcha'] = [
        'value'    => null,
        'is_valid' => false,
        'errors'   => 'CAPTCHA token is missing',
      ];
      return $this;
    }

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
   * @deprecated Use CsrfGuard directly: (new CsrfGuard())->createToken($form).
   *   Kept for back-compat; delegates to CsrfGuard.
   *
   * @param string $form Unique identifier for the form (e.g. 'contact', 'login').
   */
  public function createToken(string $form = 'default'): string {
    $token = (new CsrfGuard())->createToken($form);
    $this->state['token'] = $token;
    return $token;
  }

  /**
   * @deprecated Use CsrfGuard directly: (new CsrfGuard())->checkToken($token, $form).
   *   Kept for back-compat; delegates to CsrfGuard.
   *
   * @param string $form Must match the $form used in createToken().
   */
  public function checkToken(string $token, string $form = 'default'): bool {
    return (new CsrfGuard())->checkToken($token, $form);
  }

  private function createState(): array {
    return [
      'value' => null,
      'errors' => null,
      'is_valid' => false,
    ];
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
