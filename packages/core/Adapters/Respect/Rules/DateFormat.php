<?php

namespace SchemableValidator\Adapters\Respect\Rules;

use Respect\Validation\Rules\AbstractRule;
use SchemableValidator\Validation\Formats;

/**
 * format: "date" (RFC 3339 full-date) — delegates to Formats::matches('date'),
 * which applies the same regex + CalendarDate check as the FE constraint.ts.
 */
final class DateFormat extends AbstractRule {
  public function validate($input): bool {
    if (!is_string($input)) {
      return false;
    }
    return Formats::matches('date', $input) === true;
  }
}
