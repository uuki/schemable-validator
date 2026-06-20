<?php

namespace SchemableValidator\Adapters\Respect;

use Respect\Validation\Validator as v;
use SchemableValidator\Adapters\Respect\RawRespectSchema;

/**
 * Optional Respect driver: factory for (B) escape-hatch fields backed by
 * Respect/Validation rules that have no JSON Schema (IR) form. Requires the
 * optional `respect/validation` dependency.
 *
 * This is the canonical home for Respect-specific escape hatches and the model
 * for third-party engine drivers: a small facade returning a CustomField
 * implementation (here RawRespectSchema, whose evaluate() runs the Respect rule).
 * The core never needs this — SV::custom() covers the dependency-free case.
 *
 *   use SchemableValidator\Adapters\Respect\RespectRules as R;
 *   SV::object([ 'iban' => R::iban(), 'zip' => R::postalCode('JP') ]);
 */
final class RespectRules {
  /**
   * Wrap an arbitrary Respect/Validation rule.
   *
   * @param v|object $rule  A Respect\Validation\Validator instance. Accepts
   *                        object to support callers that strip the type hint
   *                        (e.g. SV::respect()).
   */
  public static function rule(object $rule): RawRespectSchema {
    return new RawRespectSchema($rule);
  }

  /** Country-specific postal code validation. */
  public static function postalCode(string $countryCode): RawRespectSchema {
    return new RawRespectSchema(v::postalCode($countryCode));
  }

  /** Credit card number validation via the Luhn algorithm. */
  public static function creditCard(string ...$brands): RawRespectSchema {
    return new RawRespectSchema(empty($brands) ? v::creditCard() : v::creditCard(...$brands));
  }

  /** IBAN (International Bank Account Number) validation. */
  public static function iban(): RawRespectSchema {
    return new RawRespectSchema(v::iban());
  }
}
