<?php

namespace SchemableValidator\Rules;

use Respect\Validation\Rules\AbstractRule;
use SchemableValidator\Validation\CalendarDate;

/**
 * format: "date-time" (RFC 3339) — fast regex mirroring
 * packages/client/src/constraint.ts FORMAT_RE['date-time'], plus the same
 * pure-arithmetic calendar check on the date portion as DateFormat.
 */
final class DateTimeFormat extends AbstractRule {
  private const PATTERN = '/^(\d{4})-(\d{2})-(\d{2})T\d{2}:\d{2}:(?:[0-5]\d|60)(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/';

  public function validate($input): bool {
    if (!is_string($input)) {
      return false;
    }
    if (!preg_match(self::PATTERN, $input, $m)) {
      return false;
    }
    return CalendarDate::isValid((int) $m[1], (int) $m[2], (int) $m[3]);
  }
}
