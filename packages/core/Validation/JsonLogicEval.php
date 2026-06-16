<?php

namespace SchemableValidator\Validation;

/**
 * Minimal JSONLogic evaluator for the x-when subset.
 *
 * Supported operators: ===, !==, >=, <=, >, <, and, or
 * Values: scalar literals + {"var": "fieldName"} references.
 *
 * Semantics match the existing Validator conditional behavior:
 *   - ===, !== compare as strings (form data is always string-typed)
 *   - >=, <=, >, <  compare as floats via Coercion Contract v1 rules
 *     (hex/octal/binary prefix rejected; leading/trailing whitespace trimmed)
 */
final class JsonLogicEval {
  /**
   * Evaluate a JSONLogic condition against a data map.
   *
   * @param array<string, mixed> $condition JSONLogic condition object
   * @param array<string, mixed> $data      Input data (form field values)
   */
  public static function apply(array $condition, array $data): bool {
    $op   = (string) array_key_first($condition);
    $args = $condition[$op];

    if ($op === 'and') {
      foreach ($args as $c) {
        if (!self::apply($c, $data)) {
          return false;
        }
      }
      return true;
    }

    if ($op === 'or') {
      foreach ($args as $c) {
        if (self::apply($c, $data)) {
          return true;
        }
      }
      return false;
    }

    $a = self::resolve($args[0], $data);
    $b = self::resolve($args[1], $data);

    if ($op === '===') return self::toStr($a) === self::toStr($b);
    if ($op === '!==') return self::toStr($a) !== self::toStr($b);
    if ($op === '>=')  return self::toFloat($a) >= self::toFloat($b);
    if ($op === '<=')  return self::toFloat($a) <= self::toFloat($b);
    if ($op === '>')   return self::toFloat($a) >  self::toFloat($b);
    if ($op === '<')   return self::toFloat($a) <  self::toFloat($b);

    return false;
  }

  /** Resolve a JSONLogic value: {"var": "name"} or a literal. */
  private static function resolve($value, array $data) {
    if (is_array($value) && array_key_exists('var', $value)) {
      return $data[$value['var']] ?? null;
    }
    return $value;
  }

  /** Convert any value to string, joining arrays with commas. */
  private static function toStr($value): string {
    if (is_array($value)) {
      return implode(',', array_map('strval', $value));
    }
    return (string) ($value ?? '');
  }

  /**
   * Convert any value to float, mirroring Coercion Contract v1.
   * Rejects hex/octal/binary prefixes; trims whitespace before parsing.
   */
  private static function toFloat($value): float {
    $str = trim(self::toStr($value));
    if ($str !== '' && is_numeric($str) && !preg_match('/^[+-]?0[xXoObB]/i', $str)) {
      return (float) $str;
    }
    return 0.0;
  }
}
