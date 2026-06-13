<?php

namespace SchemableValidator\Schema;

use Respect\Validation\Validator as v;

final class IntegerSchema extends AbstractFieldSchema {
  /** @var array */
  private $bounds = [];

  /** @return $this */
  public function min(int $n) {
    $this->bounds[] = ['rule' => 'min', 'args' => [$n]];
    return $this;
  }

  /** @return $this */
  public function max(int $n) {
    $this->bounds[] = ['rule' => 'max', 'args' => [$n]];
    return $this;
  }

  public function toRespect(): v {
    $chain = v::create();
    $chain->addRule(RuleMapper::resolve('integer', [])->respect);
    foreach ($this->bounds as $bound) {
      $chain->addRule(RuleMapper::resolve($bound['rule'], $bound['args'])->respect);
    }
    return $chain;
  }

  public function toJsonSchema(): array {
    $schema = ['type' => 'integer'];
    foreach ($this->bounds as $bound) {
      $mapping = RuleMapper::resolve($bound['rule'], $bound['args']);
      $schema  = array_merge($schema, $mapping->jsonSchema);
    }
    return $this->applyNullable($schema);
  }
}
