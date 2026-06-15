<?php

namespace SchemableValidator\Rules;

use Respect\Validation\Rules\AbstractRule;

/**
 * format: "time" (RFC 3339 full-time) — fast regex mirroring
 * packages/client/src/constraint.ts FORMAT_RE.time. Seconds accept 00-60
 * to allow for leap seconds, matching FORMAT_RE['date-time'].
 */
final class TimeFormat extends AbstractRule {
  private const PATTERN = '/^(?:[01]\d|2[0-3]):[0-5]\d:(?:[0-5]\d|60)(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?$/';

  public function validate($input): bool {
    if (!is_string($input)) {
      return false;
    }
    return preg_match(self::PATTERN, $input) === 1;
  }
}
