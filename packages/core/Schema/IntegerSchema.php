<?php

namespace SchemableValidator\Schema;

final class IntegerSchema extends AbstractFieldSchema implements MappableField {
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

  public function toDescriptors(): array {
    $descriptors = [['rule' => 'integer', 'args' => []]];
    foreach ($this->bounds as $bound) {
      $descriptors[] = $bound;
    }
    return $descriptors;
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
