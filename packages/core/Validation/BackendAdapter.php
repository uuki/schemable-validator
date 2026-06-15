<?php

namespace SchemableValidator\Validation;

/**
 * A BackendAdapter compiles a JSON Schema (2020-12, object schema with
 * `properties`/`required`) into an ExecutableValidator.
 *
 * Implementations own all knowledge of a specific validation engine
 * (Respect, Opis, ...). The neutral IR (JSON Schema + x-* extensions) is the
 * only thing that crosses the adapter boundary.
 */
interface BackendAdapter {
  public function compile(array $jsonSchema): ExecutableValidator;
}
