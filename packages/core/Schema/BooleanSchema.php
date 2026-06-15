<?php

namespace SchemableValidator\Schema;

final class BooleanSchema extends AbstractFieldSchema implements MappableField {
  public function toDescriptors(): array {
    return [['rule' => 'boolean', 'args' => []]];
  }

  public function toJsonSchema(): array {
    return $this->applyNullable(['type' => 'boolean']);
  }
}
