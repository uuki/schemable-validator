<?php

namespace SchemableValidator\Validation;

/**
 * Dependency-free format assertions mirroring FORMAT_RE in
 * packages/client/src/constraint.ts byte-for-byte (no Respect, no ext-filter).
 *
 * Used by NativeExecutableValidator so the native BE path produces the same
 * accept/reject decisions as the FE for every `format`. date/date-time also
 * run the pure-arithmetic CalendarDate check, exactly like the FE.
 *
 * Unsafe code points excluded from email/uri (homograph / control guards),
 * matching the FE _UNSAFE set: U+0000–U+001F, U+007F, U+200B–U+200D, U+FEFF.
 */
final class Formats {
  private const UNSAFE = '\x{0000}-\x{001f}\x{007f}\x{200b}-\x{200d}\x{feff}';

  /**
   * @return bool|null  true/false if $format is known, null if unrecognized
   *                    (unknown formats are a no-op on both stacks).
   */
  public static function matches(string $format, string $value): ?bool {
    $local  = '[^\s@' . self::UNSAFE . ']+';
    $path   = '[^\s' . self::UNSAFE . ']+';

    switch ($format) {
      case 'email':
        return preg_match('/^' . $local . '@' . $local . '\.' . $local . '$/u', $value) === 1;
      case 'uri':
        return preg_match('/^https?:\/\/' . $path . '$/u', $value) === 1;
      case 'date':
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $value, $m) !== 1) {
          return false;
        }
        return CalendarDate::isValid((int) $m[1], (int) $m[2], (int) $m[3]);
      case 'date-time':
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T\d{2}:\d{2}:(?:[0-5]\d|60)(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value, $m) !== 1) {
          return false;
        }
        return CalendarDate::isValid((int) $m[1], (int) $m[2], (int) $m[3]);
      case 'time':
        return preg_match('/^(?:[01]\d|2[0-3]):(?:[0-5]\d):(?:[0-5]\d|60)(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?$/', $value) === 1;
      case 'uuid':
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
      case 'ipv4':
        return preg_match('/^(?:(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(?:25[0-5]|2[0-4]\d|[01]?\d\d?)$/', $value) === 1;
      case 'ipv6':
        return preg_match('/^(([0-9a-f]{1,4}:){7}[0-9a-f]{1,4}|([0-9a-f]{1,4}:){1,7}:|([0-9a-f]{1,4}:){1,6}:[0-9a-f]{1,4}|([0-9a-f]{1,4}:){1,5}(:[0-9a-f]{1,4}){1,2}|([0-9a-f]{1,4}:){1,4}(:[0-9a-f]{1,4}){1,3}|([0-9a-f]{1,4}:){1,3}(:[0-9a-f]{1,4}){1,4}|([0-9a-f]{1,4}:){1,2}(:[0-9a-f]{1,4}){1,5}|[0-9a-f]{1,4}:((:[0-9a-f]{1,4}){1,6})|:((:[0-9a-f]{1,4}){1,7}|:))$/i', $value) === 1;
      case 'hostname':
        return preg_match('/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $value) === 1;
      default:
        return null;
    }
  }
}
