<?php

namespace SchemableValidator\Validation;

/**
 * ASCII-limited value transform catalog.
 *
 * catalog (closed set — not Turing-complete):
 *   trim        — strip TAB/LF/CR/SPACE from both ends
 *   toLowerCase — ASCII A-Z → a-z
 *   toUpperCase — ASCII a-z → A-Z
 *
 * PHP/TS parity:
 *   trim       — ASCII 4-char set (not PHP's default 6-char set)
 *   lower/upper — ASCII-only (Unicode case conversion differs between PHP and JS)
 */
final class Transform {
  /** @param string[] $transforms */
  public static function apply(string $value, array $transforms): string {
    foreach ($transforms as $name) {
      switch ($name) {
        case 'trim':
          $value = ltrim(rtrim($value, "\t\n\r "), "\t\n\r ");
          break;
        case 'toLowerCase':
          $value = strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
          break;
        case 'toUpperCase':
          $value = strtr($value, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
          break;
        default:
          throw new \InvalidArgumentException("Unknown x-transform: '{$name}'");
      }
    }
    return $value;
  }
}
