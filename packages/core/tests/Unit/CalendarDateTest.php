<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SchemableValidator\Validation\CalendarDate;

/**
 * Pure-arithmetic Gregorian calendar realism check, mirrored by
 * packages/client/src/constraint.ts's isValidCalendarDate so BE/FE agree on
 * whether a date like "2026-02-30" exists.
 */
class CalendarDateTest extends TestCase {
  public function test_valid_ordinary_date(): void {
    $this->assertTrue(CalendarDate::isValid(2024, 1, 15));
  }

  public function test_feb_29_valid_in_leap_year(): void {
    $this->assertTrue(CalendarDate::isValid(2024, 2, 29));
  }

  public function test_feb_29_invalid_in_non_leap_year(): void {
    $this->assertFalse(CalendarDate::isValid(2023, 2, 29));
  }

  public function test_feb_29_invalid_in_century_non_leap_year(): void {
    $this->assertFalse(CalendarDate::isValid(1900, 2, 29));
  }

  public function test_feb_29_valid_in_400_divisible_year(): void {
    $this->assertTrue(CalendarDate::isValid(2000, 2, 29));
  }

  public function test_feb_30_is_invalid(): void {
    $this->assertFalse(CalendarDate::isValid(2026, 2, 30));
  }

  public function test_april_31_is_invalid(): void {
    $this->assertFalse(CalendarDate::isValid(2024, 4, 31));
  }

  public function test_december_31_is_valid(): void {
    $this->assertTrue(CalendarDate::isValid(2024, 12, 31));
  }

  public function test_month_out_of_range_is_invalid(): void {
    $this->assertFalse(CalendarDate::isValid(2024, 13, 1));
    $this->assertFalse(CalendarDate::isValid(2024, 0, 1));
  }

  public function test_day_zero_is_invalid(): void {
    $this->assertFalse(CalendarDate::isValid(2024, 1, 0));
  }
}
