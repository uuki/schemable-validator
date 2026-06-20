<?php

namespace SchemableValidator\Adapters\Respect\Rules;

use Respect\Validation\Rules\AbstractRule;
use SchemableValidator\Validation\Formats;

/**
 * format: "date-time" (RFC 3339) — delegates to Formats::matches('date-time'),
 * which applies the same regex + CalendarDate check as the FE constraint.ts.
 */
final class DateTimeFormat extends AbstractRule {
  public function validate($input): bool {
    if (!is_string($input)) {
      return false;
    }
    return Formats::matches('date-time', $input) === true;
  }
}
