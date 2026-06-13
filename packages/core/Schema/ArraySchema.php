<?php

namespace SchemableValidator\Schema;

use Respect\Validation\Validator as v;

final class ArraySchema extends AbstractFieldSchema {
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

  public function toRespect(): v {
    $chain = v::create();
    $chain->addRule(v::each($this->items->toRespect()));
    if ($this->minItems !== null) {
      $chain->addRule(v::length($this->minItems, null));
    }
    if ($this->maxItems !== null) {
      $chain->addRule(v::length(null, $this->maxItems));
    }
    return $chain;
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
