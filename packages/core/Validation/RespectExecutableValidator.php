<?php

namespace SchemableValidator\Validation;

use Respect\Validation\Validator as v;
use SchemableValidator\I18n\DefaultMessages;
use SchemableValidator\I18n\MessageDict;
use SchemableValidator\Validation\Adapters\RespectAdapter;

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
          // Resolution order (highest first):
          //   user MessageDict (by neutral ruleId)
          //   > inline errorMessage (by JSON Schema keyword, per field)
          //   > engine-neutral canonical catalog (DefaultMessages, by neutral ruleId)
          //   > Respect's own message (last-resort, for rules with no neutral mapping)
          // {var} placeholders are interpolated on whichever template wins.
          $neutral = $vio['neutralRuleId'];
          $keyword = $vio['keyword'];

          $catalogDefault = $neutral !== null ? DefaultMessages::template($neutral) : null;
          $template = $catalogDefault ?? $vio['message'];

          if ($keyword !== null && isset($this->inlineMessages[$field][$keyword])) {
            $template = $this->inlineMessages[$field][$keyword];
          }

          $resolved[] = $this->dict !== null
            ? $this->dict->resolve($field, $neutral ?? $vio['ruleId'], $template, $vio['vars'])
            : MessageDict::interpolate($template, $vio['vars']);
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
