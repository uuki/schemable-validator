<?php

namespace SchemableValidator\Schema;

final class EnumSchema extends AbstractFieldSchema implements MappableField {
  /** @var array */
  private $values;

  public function __construct(array $values) {
    $this->values = $values;
  }

  public function toDescriptors(): array {
    return [['rule' => 'in', 'args' => [$this->values]]];
  }

  public function toJsonSchema(): array {
    return $this->applyNullable(['type' => 'string', 'enum' => $this->values]);
  }
}
