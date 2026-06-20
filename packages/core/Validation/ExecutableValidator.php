<?php

namespace SchemableValidator\Validation;

/**
 * Result of BackendAdapter::compile(). Validates a data array against the
 * compiled schema, returning the {value, is_valid, errors} shape per field
 * (mirrors Validator::createState()).
 */
interface ExecutableValidator {
  /**
   * @param array<string, mixed> $data
   * @return array<string, array{value: mixed, is_valid: bool, errors: ?string}>
   */
  public function validate(array $data): array;
}
