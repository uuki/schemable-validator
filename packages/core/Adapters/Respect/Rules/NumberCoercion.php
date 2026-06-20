<?php

namespace SchemableValidator\Adapters\Respect\Rules;

use Respect\Validation\Rules\AbstractRule;
use SchemableValidator\Validation\Coercion;

/**
 * Coercion Contract v1 `type: number`: accepts native ints/floats and
 * strings for which Coercion::acceptsNumber() is true (e.g. "3.14").
 */
final class NumberCoercion extends AbstractRule {
  public function validate($input): bool {
    if (is_int($input) || is_float($input)) {
      return is_finite((float) $input);
    }
    if (is_string($input)) {
      return Coercion::acceptsNumber($input);
    }
    return false;
  }
}
