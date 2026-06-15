<?php

namespace SchemableValidator\Rules;

use Respect\Validation\Rules\AbstractRule;
use SchemableValidator\Validation\CalendarDate;

/**
 * format: "date" (RFC 3339 full-date) — fast regex (YYYY-MM-DD) plus the
 * same pure-arithmetic calendar check as packages/client/src/constraint.ts,
 * so "2026-02-30" is rejected on both sides without DateTime/checkdate.
 */
final class DateFormat extends AbstractRule {
  private const PATTERN = '/^(\d{4})-(\d{2})-(\d{2})$/';

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
