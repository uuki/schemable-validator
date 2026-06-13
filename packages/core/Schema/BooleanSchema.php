<?php

namespace SchemableValidator\Schema;

use Respect\Validation\Validator as v;

final class BooleanSchema extends AbstractFieldSchema {
  public function toRespect(): v {
    $chain = v::create();
    $chain->addRule(RuleMapper::resolve('boolean', [])->respect);
    return $chain;
  }

  public function toJsonSchema(): array {
    return $this->applyNullable(['type' => 'boolean']);
  }
}
