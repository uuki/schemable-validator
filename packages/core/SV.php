<?php

namespace SchemableValidator;

use Respect\Validation\Validator as v;
use SchemableValidator\Schema\AbstractFieldSchema;
use SchemableValidator\Schema\ArraySchema;
use SchemableValidator\Schema\BooleanSchema;
use SchemableValidator\Schema\EnumSchema;
use SchemableValidator\Schema\FieldRef;
use SchemableValidator\Schema\FileSchema;
use SchemableValidator\Schema\IntegerSchema;
use SchemableValidator\Schema\NumberSchema;
use SchemableValidator\Schema\RawRespectSchema;
use SchemableValidator\Schema\StringSchema;
use SchemableValidator\Schema\WhenExpr;

final class SV {
  public static function object(array $fields): SchemaBuilder {
    return new SchemaBuilder($fields);
  }

  public static function string(): StringSchema {
    return new StringSchema();
  }

  public static function integer(): IntegerSchema {
    return new IntegerSchema();
  }

  public static function number(): NumberSchema {
    return new NumberSchema();
  }

  public static function boolean(): BooleanSchema {
    return new BooleanSchema();
  }

  public static function enum(array $values): EnumSchema {
    return new EnumSchema($values);
  }

  public static function file(array $accept = []): FileSchema {
    return new FileSchema($accept);
  }

  /** Array field: validates each element with the given item schema. */
  public static function array(AbstractFieldSchema $items): ArraySchema {
    return new ArraySchema($items);
  }

  /** Escape hatch: wrap an arbitrary Respect/Validation rule. */
  public static function respect(v $rule): RawRespectSchema {
    return new RawRespectSchema($rule);
  }

  /** Country-specific postal code validation (x-unmapped-fields). */
  public static function postalCode(string $countryCode): RawRespectSchema {
    return new RawRespectSchema(v::postalCode($countryCode));
  }

  /** Credit card number validation via Luhn algorithm (x-unmapped-fields). */
  public static function creditCard(string ...$brands): RawRespectSchema {
    return new RawRespectSchema(empty($brands) ? v::creditCard() : v::creditCard(...$brands));
  }

  /** IBAN (International Bank Account Number) validation (x-unmapped-fields). */
  public static function iban(): RawRespectSchema {
    return new RawRespectSchema(v::iban());
  }

  // ── Conditional expression builders ──────────────────────────

  /** Refer to another field's runtime value in a when() condition. */
  public static function field(string $name): FieldRef {
    return new FieldRef($name);
  }

  /**
   * "field === $value" expression for when().
   * $value may be a scalar or SV::field('name').
   *
   * @param scalar|FieldRef $value
   */
  public static function equal($value): WhenExpr {
    return new WhenExpr('===', $value);
  }

  /**
   * "field !== $value" expression for when().
   * $value may be a scalar or SV::field('name').
   *
   * @param scalar|FieldRef $value
   */
  public static function notEqual($value): WhenExpr {
    return new WhenExpr('!==', $value);
  }

  /**
   * "field >= $value" expression for when(). Operand is compared numerically.
   * @param int|float|FieldRef $value
   */
  public static function greaterThanOrEqual($value): WhenExpr {
    return new WhenExpr('>=', $value);
  }

  /**
   * "field <= $value" expression for when(). Operand is compared numerically.
   * @param int|float|FieldRef $value
   */
  public static function lessThanOrEqual($value): WhenExpr {
    return new WhenExpr('<=', $value);
  }

  /**
   * "field > $value" expression for when(). Operand is compared numerically.
   * @param int|float|FieldRef $value
   */
  public static function greaterThan($value): WhenExpr {
    return new WhenExpr('>', $value);
  }

  /**
   * "field < $value" expression for when(). Operand is compared numerically.
   * @param int|float|FieldRef $value
   */
  public static function lessThan($value): WhenExpr {
    return new WhenExpr('<', $value);
  }
}
