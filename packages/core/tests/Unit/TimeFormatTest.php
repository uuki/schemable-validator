<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SchemableValidator\Rules\TimeFormat;

/**
 * format: "time" (RFC 3339 full-time) — mirrored by
 * packages/client/src/constraint.ts FORMAT_RE.time so BE/FE agree on
 * accepted values, including the :60 leap-second allowance.
 */
class TimeFormatTest extends TestCase {
  /** @dataProvider validProvider */
  public function test_accepts_valid_time(string $value): void {
    $this->assertTrue((new TimeFormat())->validate($value));
  }

  public function validProvider(): array {
    return [
      'midnight'       => ['00:00:00'],
      'end of day'     => ['23:59:59'],
      'ordinary time'  => ['12:30:45'],
      'leap second'    => ['23:59:60'],
      'fractional sec' => ['12:30:45.123'],
      'utc suffix'     => ['12:30:45Z'],
      'offset suffix'  => ['12:30:45+09:00'],
      'fractional+utc' => ['12:30:45.999Z'],
    ];
  }

  /** @dataProvider invalidProvider */
  public function test_rejects_invalid_time($value): void {
    $this->assertFalse((new TimeFormat())->validate($value));
  }

  public function invalidProvider(): array {
    return [
      'hour out of range'       => ['24:00:00'],
      'minute out of range'     => ['12:60:00'],
      'second beyond leap'      => ['12:30:61'],
      'missing seconds'         => ['12:30'],
      'not a time'              => ['not-a-time'],
      'empty string'            => [''],
      'non-string input (int)'  => [123],
      'non-string input (null)' => [null],
      'non-string input (array)' => [['12:30:00']],
    ];
  }
}
