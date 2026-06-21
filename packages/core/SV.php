<?php

namespace SchemableValidator;

use SchemableValidator\Orchestration\SchemaBuilder;
use SchemableValidator\Schema\AbstractFieldSchema;
use SchemableValidator\Schema\ArraySchema;
use SchemableValidator\Schema\BooleanSchema;
use SchemableValidator\Schema\CustomFieldSchema;
use SchemableValidator\Schema\EnumSchema;
use SchemableValidator\Schema\FieldRef;
use SchemableValidator\Schema\FileSchema;
use SchemableValidator\Schema\IntegerSchema;
use SchemableValidator\Schema\NumberSchema;
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

  /**
   * @param string[] $accept           Allowed MIME types (empty = any).
   * @param array<string, int> $imageConstraints  Optional image constraints for ImageDriver:
   *                                   maxWidth, maxHeight, minWidth, minHeight (px), maxSize (bytes).
   */
  public static function file(array $accept = [], array $imageConstraints = []): FileSchema {
    return new FileSchema($accept, $imageConstraints);
  }

  /** Array field: validates each element with the given item schema. */
  public static function array(AbstractFieldSchema $items): ArraySchema {
    return new ArraySchema($items);
  }

  /**
   * Dependency-free escape hatch: validate with a custom predicate that has no
   * JSON Schema form. $predicate is `callable(mixed $value): bool`.
   */
  public static function custom(callable $predicate, string $message = 'is invalid'): CustomFieldSchema {
    return new CustomFieldSchema($predicate, $message);
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
