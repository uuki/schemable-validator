<?php

namespace SchemableValidator\Validation\Adapters;

use SchemableValidator\I18n\MessageDict;
use SchemableValidator\Validation\BackendAdapter;
use SchemableValidator\Validation\ExecutableValidator;
use SchemableValidator\Validation\NativeExecutableValidator;

/**
 * Dependency-free BackendAdapter: compiles JSON Schema 2020-12 into a
 * NativeExecutableValidator that ports the FE constraint.ts semantics to PHP.
 *
 * Unlike OpisAdapter (strict, no coercion) this honors Coercion Contract v1, so
 * it is a genuine Respect-free drop-in for the form-string ("parity") path —
 * proven by NativeConformanceTest running every conformance/*.json fixture and
 * matching the FE-authored `expected` blocks.
 */
final class NativeAdapter implements BackendAdapter {
  public function compile(array $jsonSchema, ?MessageDict $dict = null): ExecutableValidator {
    $properties     = $jsonSchema['properties'] ?? [];
    $required       = $jsonSchema['required'] ?? [];
    $inlineMessages = [];

    foreach ($properties as $name => $prop) {
      if (!empty($prop['errorMessage'])) {
        $inlineMessages[$name] = $prop['errorMessage'];
      }
    }

    return new NativeExecutableValidator($properties, $required, $inlineMessages, $dict);
  }
}
