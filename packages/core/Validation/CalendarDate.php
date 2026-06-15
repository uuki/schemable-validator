<?php

namespace SchemableValidator\Validation;

/**
 * Pure-arithmetic Gregorian calendar realism check (leap-year rule +
 * days-in-month table). No DateTime/checkdate/strtotime — mirrors the
 * algorithm in packages/client/src/constraint.ts so BE/FE agree on whether
 * a date like "2026-02-30" exists, independent of platform date libraries.
 *
 * See .claude/logs/design-direction.md (2026-06-15, format-assertion fix).
 */
final class CalendarDate {
  private const DAYS_IN_MONTH = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

  public static function isValid(int $year, int $month, int $day): bool {
    if ($month < 1 || $month > 12) {
      return false;
    }
    if ($day < 1) {
      return false;
    }

    $max = self::DAYS_IN_MONTH[$month - 1];
    if ($month === 2 && self::isLeapYear($year)) {
      $max = 29;
    }

    return $day <= $max;
  }

  private static function isLeapYear(int $year): bool {
    return $year % 4 === 0 && ($year % 100 !== 0 || $year % 400 === 0);
  }
}
