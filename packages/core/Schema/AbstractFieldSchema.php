<?php

namespace SchemableValidator\Schema;

abstract class AbstractFieldSchema {
  /** @var bool */
  protected $required = true;

  /** @var bool */
  protected $nullable = false;

  /** @return $this */
  public function optional() {
    $this->required = false;
    return $this;
  }

  /** @return $this */
  public function nullable() {
    $this->nullable = true;
    return $this;
  }

  public function isRequired(): bool {
    return $this->required;
  }

  public function isMappable(): bool {
    return true;
  }

  abstract public function toJsonSchema(): array;

  protected function applyNullable(array $schema): array {
    if ($this->nullable && isset($schema['type']) && is_string($schema['type'])) {
      $schema['type'] = [$schema['type'], 'null'];
    }
    return $schema;
  }
}
