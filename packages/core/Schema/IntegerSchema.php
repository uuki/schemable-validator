<?php

namespace SchemableValidator\Schema;

final class IntegerSchema extends AbstractNumericSchema {
  protected function typeName(): string {
    return 'integer';
  }

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
}
