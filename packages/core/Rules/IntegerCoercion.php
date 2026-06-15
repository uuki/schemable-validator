<?php

namespace SchemableValidator\Rules;

use Respect\Validation\Rules\AbstractRule;
use SchemableValidator\Validation\Coercion;

/**
 * Coercion Contract v1 `type: integer`: accepts native ints, integer-valued
 * floats, and strings for which Coercion::acceptsInteger() is true (e.g. "42").
 */
final class IntegerCoercion extends AbstractRule {
  public function validate($input): bool {
    if (is_int($input)) {
      return true;
    }
    if (is_float($input)) {
      return is_finite($input) && fmod($input, 1.0) === 0.0;
    }
    if (is_string($input)) {
      return Coercion::acceptsInteger($input);
    }
    return false;
  }
}
