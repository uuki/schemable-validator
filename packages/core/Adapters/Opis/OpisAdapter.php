<?php

namespace SchemableValidator\Adapters\Opis;

use SchemableValidator\Adapters\AdapterHelper;
use SchemableValidator\I18n\MessageDict;
use SchemableValidator\Validation\BackendAdapter;
use SchemableValidator\Validation\ExecutableValidator;

/**
 * Respect-free BackendAdapter: compiles JSON Schema 2020-12 directly via
 * opis/json-schema (strict structural validation, no Coercion Contract).
 *
 * Exists to prove the BackendAdapter boundary is genuinely swappable and to
 * provide a respect-independent execution path for typed-JSON input. NOT a
 * drop-in replacement for RespectAdapter on form-string ("parity") fixtures
 * — see OpisExecutableValidator.
 */
final class OpisAdapter implements BackendAdapter {
  public function compile(array $jsonSchema, ?MessageDict $dict = null): ExecutableValidator {
    return new OpisExecutableValidator(
      $jsonSchema['properties'] ?? [],
      $jsonSchema['required'] ?? [],
      AdapterHelper::extractInlineMessages($jsonSchema),
      $dict
    );
  }
}
