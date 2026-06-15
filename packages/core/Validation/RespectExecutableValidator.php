<?php

namespace SchemableValidator\Validation;

use Respect\Validation\Validator as v;
use SchemableValidator\I18n\MessageDict;
use SchemableValidator\Validation\Adapters\RespectAdapter;

final class RespectExecutableValidator implements ExecutableValidator {
  /** @var array<string, v> */
  private $schema;

  private ?MessageDict $dict;

  /** @param array<string, v> $schema */
  public function __construct(array $schema, ?MessageDict $dict = null) {
    $this->schema = $schema;
    $this->dict   = $dict;
  }

  public function validate(array $data): array {
    $result = [];

    foreach ($this->schema as $field => $validator) {
      $value    = $data[$field] ?? null;
      $newState = ['value' => $value, 'errors' => null, 'is_valid' => false];

      try {
        $validator->assert($value);
      } catch (\Respect\Validation\Exceptions\ValidationException $e) {
        $ruleMessages = RespectAdapter::extractRuleMessages($e);
        $resolved     = [];
        foreach ($ruleMessages as $ruleId => $defaultMsg) {
          $resolved[] = $this->dict !== null
            ? $this->dict->resolve($field, $ruleId, $defaultMsg)
            : $defaultMsg;
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
