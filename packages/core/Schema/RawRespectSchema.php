<?php

namespace SchemableValidator\Schema;

use Respect\Validation\Validator as v;
use SchemableValidator\I18n\MessageDict;
use SchemableValidator\Validation\CustomField;
use SchemableValidator\Validation\RespectExecutableValidator;

/**
 * (B) escape hatch wrapping an arbitrary Respect/Validation rule (SV::respect,
 * and the postalCode/creditCard/iban presets). Implements CustomField, so the
 * core executes it through evaluate() without knowing it is Respect-backed.
 *
 * NOTE: this is the in-core Respect driver. Pulling it (and the SV factory
 * methods) into a separate optional Drivers\Respect namespace is the next step
 * toward making respect/validation a fully optional dependency.
 */
final class RawRespectSchema extends AbstractFieldSchema implements CustomField {
  /** @var object Respect\Validation\Validator instance. */
  private $rule;

  /**
   * @param object $rule  A Respect\Validation\Validator instance. Type hint
   *                      is object so loading this class does not require the
   *                      respect/validation package at parse time.
   */
  public function __construct(object $rule) {
    $this->rule = $rule;
  }

  public function isMappable(): bool {
    return false;
  }

  /** @return v */
  public function toRespect() {
    return $this->rule;
  }

  public function toJsonSchema(): array {
    return [];
  }

  public function evaluate(string $field, $value, ?MessageDict $dict): array {
    // Reuse the shared Respect executable so message resolution (neutral ruleId
    // > inline > catalog > engine, plus dict) is identical to the mappable path.
    $chain = $this->isRequired() ? $this->rule : v::optional($this->rule);
    $result = (new RespectExecutableValidator([$field => $chain], $dict))->validate([$field => $value]);
    return $result[$field];
  }
}
