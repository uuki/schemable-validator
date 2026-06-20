<?php

namespace SchemableValidator\Schema;

use SchemableValidator\I18n\MessageDict;
use SchemableValidator\Validation\CustomField;

/**
 * Dependency-free escape hatch (SV::custom). Wraps a user predicate
 * `callable(mixed $value): bool` for validation logic that cannot be expressed
 * in JSON Schema and needs no external engine.
 *
 * On the frontend this field has no IR, so it is passed through (x-unmapped-fields)
 * or implemented via .refine() (x-custom-fields) — see docs.
 */
final class CustomFieldSchema extends AbstractFieldSchema implements CustomField {
  /** @var callable */
  private $predicate;

  /** @var string */
  private $message;

  public function __construct(callable $predicate, string $message = 'is invalid') {
    $this->predicate = $predicate;
    $this->message   = $message;
  }

  public function isMappable(): bool {
    return false;
  }

  public function toJsonSchema(): array {
    return [];
  }

  public function evaluate(string $field, $value, ?MessageDict $dict): array {
    // Optional fields skip validation when empty (mirrors the scalar contract).
    $isEmpty = $value === null || $value === '' || $value === [];
    if (!$this->isRequired() && $isEmpty) {
      return ['value' => $value, 'is_valid' => true, 'errors' => null];
    }

    $isValid = (bool) ($this->predicate)($value);
    if ($isValid) {
      return ['value' => $value, 'is_valid' => true, 'errors' => null];
    }

    $errors = $dict !== null
      ? $dict->resolve($field, 'custom', $this->message)
      : $this->message;
    return ['value' => $value, 'is_valid' => false, 'errors' => $errors];
  }
}
