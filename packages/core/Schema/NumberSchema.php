<?php

namespace SchemableValidator\Schema;

final class NumberSchema extends AbstractFieldSchema implements MappableField {
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

  public function toDescriptors(): array {
    $descriptors = [['rule' => 'number', 'args' => []]];
    foreach ($this->bounds as $bound) {
      $descriptors[] = $bound;
    }
    return $descriptors;
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
