<?php

namespace SchemableValidator\Schema;

use Respect\Validation\Validator as v;

final class EnumSchema extends AbstractFieldSchema {
  /** @var array */
  private $values;

  public function __construct(array $values) {
    $this->values = $values;
  }

  public function toRespect(): v {
    $chain = v::create();
    $chain->addRule(RuleMapper::resolve('in', [$this->values])->respect);
    return $chain;
  }

  public function toJsonSchema(): array {
    return $this->applyNullable(['type' => 'string', 'enum' => $this->values]);
  }
}
