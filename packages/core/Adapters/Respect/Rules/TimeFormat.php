<?php

namespace SchemableValidator\Adapters\Respect\Rules;

use Respect\Validation\Rules\AbstractRule;
use SchemableValidator\Validation\Formats;

/**
 * format: "time" (RFC 3339 full-time) — delegates to Formats::matches('time'),
 * which applies the same regex as the FE constraint.ts FORMAT_RE.time.
 */
final class TimeFormat extends AbstractRule {
  public function validate($input): bool {
    if (!is_string($input)) {
      return false;
    }
    return Formats::matches('time', $input) === true;
  }
}
