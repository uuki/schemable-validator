<?php

namespace SchemableValidator\Schema;

use Respect\Validation\Validator as v;

final class NumberSchema extends AbstractFieldSchema {
  /** @var array */
  private $bounds = [];

  /**
   * @param int|float $n
   * @return $this
   */
  public function min($n) {
    $this->bounds[] = ['rule' => 'min', 'args' => [$n]];
    return $this;
  }

  /**
   * @param int|float $n
   * @return $this
   */
  public function max($n) {
    $this->bounds[] = ['rule' => 'max', 'args' => [$n]];
    return $this;
  }

  public function toRespect(): v {
    $chain = v::create();
    $chain->addRule(RuleMapper::resolve('number', [])->respect);
    foreach ($this->bounds as $bound) {
      $chain->addRule(RuleMapper::resolve($bound['rule'], $bound['args'])->respect);
    }
    return $chain;
  }

  public function toJsonSchema(): array {
    $schema = ['type' => 'number'];
    foreach ($this->bounds as $bound) {
      $mapping = RuleMapper::resolve($bound['rule'], $bound['args']);
      $schema  = array_merge($schema, $mapping->jsonSchema);
    }
    return $this->applyNullable($schema);
  }
}
