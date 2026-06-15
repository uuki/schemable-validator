<?php

namespace SchemableValidator\Rules;

use Respect\Validation\Rules\AbstractRule;
use SchemableValidator\Validation\Coercion;

/**
 * Coercion Contract v1 `type: boolean`: accepts native bools and strings for
 * which Coercion::acceptsBoolean() is true — {true,false,1,0,on,off,yes,no},
 * case-insensitive (e.g. "on").
 */
final class BooleanCoercion extends AbstractRule {
  public function validate($input): bool {
    if (is_bool($input)) {
      return true;
    }
    if (is_string($input)) {
      return Coercion::acceptsBoolean($input);
    }
    return false;
  }
}
