<?php

namespace SchemableValidator\Validation;

/**
 * Pure-PHP predicates implementing Coercion Contract v1 (form-string
 * acceptance for `type: integer|number|boolean`).
 *
 * toNumber() mirrors the ECMAScript StrDecimalLiteral grammar — the decimal
 * subset of `Number(string)` that real <input> values produce (optional
 * sign, digits, fraction, exponent; empty/whitespace-only -> 0).
 *
 * Documented delta from full `Number(string)`: non-decimal numeric literals
 * (0x/0o/0b) are NOT accepted, even though e.g. `Number("0x10") === 16` in
 * JS. See .claude/logs/design-direction.md (2026-06-15, W2).
 */
final class Coercion {
  private const WHITESPACE = " \t\n\r\f\v";

  private const DECIMAL_PATTERN = '/^[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?$/';

  private const ACCEPTED_BOOLEANS = ['true', 'false', '1', '0', 'on', 'off', 'yes', 'no'];

  /** Mirrors `Number(string)` for the StrDecimalLiteral subset. Non-matching input -> NAN. */
  public static function toNumber(string $value): float {
    $trimmed = trim($value, self::WHITESPACE);

    if ($trimmed === '') {
      return 0.0;
    }

    if (preg_match(self::DECIMAL_PATTERN, $trimmed) !== 1) {
      return NAN;
    }

    return (float) $trimmed;
  }

  /**
   * `Number(value)` finite AND integer (`"42"` true / `"3.14"` false / `""` false).
   * Empty string is rejected here regardless of `Number("") === 0`: empty-value
   * handling (required -> invalid, optional -> skip) is the caller's responsibility.
   */
  public static function acceptsInteger(string $value): bool {
    if ($value === '') {
      return false;
    }
    $number = self::toNumber($value);
    return is_finite($number) && fmod($number, 1.0) === 0.0;
  }

  /** `Number(value)` finite (`""` false — see acceptsInteger). */
  public static function acceptsNumber(string $value): bool {
    if ($value === '') {
      return false;
    }
    return is_finite(self::toNumber($value));
  }

  /** `{true,false,1,0,on,off,yes,no}`, case-insensitive (`""` false). */
  public static function acceptsBoolean(string $value): bool {
    if ($value === '') {
      return false;
    }
    return in_array(strtolower($value), self::ACCEPTED_BOOLEANS, true);
  }
}
