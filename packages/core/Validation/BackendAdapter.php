<?php

namespace SchemableValidator\Validation;

use SchemableValidator\I18n\MessageDict;

/**
 * A BackendAdapter compiles a JSON Schema (2020-12, object schema with
 * `properties`/`required`) into an ExecutableValidator.
 *
 * Implementations own all knowledge of a specific validation engine
 * (Respect, Opis, Native...). The neutral IR (JSON Schema + x-* extensions) is
 * the only thing that crosses the adapter boundary.
 *
 * $dict is the optional user MessageDict. The produced executable resolves
 * messages in the order: MessageDict(neutral ruleId) > inline errorMessage
 * (keyword) > canonical catalog > engine text. x-transform / x-when are applied
 * by the orchestrator (Validator), not by the executable.
 */
interface BackendAdapter {
  public function compile(array $jsonSchema, ?MessageDict $dict = null): ExecutableValidator;
}
