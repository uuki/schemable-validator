<?php

namespace SchemableValidator\Adapters\Respect;

use Respect\Validation\Validator as v;
use SchemableValidator\I18n\MessageDict;
use SchemableValidator\Validation\ExecutableValidator;
use SchemableValidator\Validation\MessageResolver;

final class RespectExecutableValidator implements ExecutableValidator {
  /** @var array<string, v> */
  private $schema;

  private ?MessageDict $dict;

  /** @var array<string, array<string, string>> field => (JSON Schema keyword => message template) */
  private array $inlineMessages;

  /**
   * @param array<string, v> $schema
   * @param array<string, array<string, string>> $inlineMessages Inline errorMessage map per field, keyed by JSON Schema keyword.
   */
  public function __construct(array $schema, ?MessageDict $dict = null, array $inlineMessages = []) {
    // Ensure Respect's factory (custom rule/exception namespaces) is configured
    // before any v->assert(); the Native default path never reaches here.
    RespectAdapter::bootstrap();
    $this->schema         = $schema;
    $this->dict           = $dict;
    $this->inlineMessages = $inlineMessages;
  }

  public function validate(array $data): array {
    $result = [];

    foreach ($this->schema as $field => $validator) {
      $value    = $data[$field] ?? null;
      $newState = ['value' => $value, 'errors' => null, 'is_valid' => false];

      try {
        $validator->assert($value);
      } catch (\Respect\Validation\Exceptions\ValidationException $e) {
        $resolved = [];
        foreach (RespectAdapter::describeViolations($e) as $vio) {
          $resolved[] = MessageResolver::resolve(
            $this->dict,
            $field,
            $vio['neutralRuleId'] ?? $vio['ruleId'],
            $vio['keyword'],
            $vio['vars'],
            $this->inlineMessages,
            $vio['message']
          );
        }
        $newState['errors'] = implode("\n", $resolved);
      }

      if ($newState['errors'] === null) {
        $newState['is_valid'] = true;
      }

      $result[$field] = $newState;
    }

    return $result;
  }
}
