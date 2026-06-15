<?php

namespace SchemableValidator\Schema;

final class ArraySchema extends AbstractFieldSchema implements MappableField {
  /** @var AbstractFieldSchema */
  private $items;

  /** @var int|null */
  private $minItems = null;

  /** @var int|null */
  private $maxItems = null;

  public function __construct(AbstractFieldSchema $items) {
    $this->items = $items;
  }

  /** @return $this */
  public function minItems(int $n) {
    $this->minItems = $n;
    return $this;
  }

  /** @return $this */
  public function maxItems(int $n) {
    $this->maxItems = $n;
    return $this;
  }

  public function toDescriptors(): array {
    $itemDescriptors = $this->items instanceof MappableField ? $this->items->toDescriptors() : [];
    $descriptors     = [['rule' => 'each', 'args' => [$itemDescriptors]]];
    if ($this->minItems !== null) {
      $descriptors[] = ['rule' => 'length', 'args' => [$this->minItems, null]];
    }
    if ($this->maxItems !== null) {
      $descriptors[] = ['rule' => 'length', 'args' => [null, $this->maxItems]];
    }
    return $descriptors;
  }

  public function toJsonSchema(): array {
    $schema = [
      'type'  => 'array',
      'items' => $this->items->toJsonSchema(),
    ];
    if ($this->minItems !== null) {
      $schema['minItems'] = $this->minItems;
    }
    if ($this->maxItems !== null) {
      $schema['maxItems'] = $this->maxItems;
    }
    return $this->applyNullable($schema);
  }
}
