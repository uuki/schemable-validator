<?php

namespace SchemableValidator\Validation;

use SchemableValidator\I18n\MessageDict;

/**
 * A (B) escape-hatch field: validation logic that has no JSON Schema (IR)
 * representation, executed as a black-box predicate. This replaces the old
 * Respect-only `UnmappableField::toRespect(): v`, so the core can run custom
 * fields without depending on any engine — a closure-backed CustomFieldSchema
 * (core, dependency-free) or an engine-backed driver (e.g. the Respect driver's
 * RawRespectField) both satisfy this contract.
 */
interface CustomField {
  public function isRequired(): bool;

  /**
   * Validate $value for field $field, optionally resolving messages via $dict.
   *
   * @return array{value: mixed, is_valid: bool, errors: ?string}
   */
  public function evaluate(string $field, $value, ?MessageDict $dict): array;
}
