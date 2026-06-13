<?php

namespace SchemableValidator\Schema;

/**
 * Comparison expression for SchemaBuilder::when().
 *
 * Bundles an operator with an operand that is either a scalar literal
 * or a FieldRef pointing to another field's runtime value.
 *
 * String operators : '===' | '!=='
 * Numeric operators: '>=' | '<=' | '>' | '<'  (operand is cast to float)
 */
final class WhenExpr {
  private const VALID_OPS = ['===', '!==', '>=', '<=', '>', '<'];

  /** @var string '===' | '!==' | '>=' | '<=' | '>' | '<' */
  public string $op;

  /** @var scalar|FieldRef */
  public $operand;

  /**
   * @param string $op      One of '===', '!==', '>=', '<=', '>', '<'
   * @param scalar|FieldRef $operand
   * @throws \InvalidArgumentException on unrecognized operator
   */
  public function __construct(string $op, $operand) {
    if (!in_array($op, self::VALID_OPS, true)) {
      throw new \InvalidArgumentException(
        "Invalid WhenExpr operator '{$op}'. Allowed: " . implode(', ', self::VALID_OPS)
      );
    }
    $this->op      = $op;
    $this->operand = $operand;
  }
}
