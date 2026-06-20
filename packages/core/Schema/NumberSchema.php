<?php

namespace SchemableValidator\Schema;

final class NumberSchema extends AbstractNumericSchema {
  protected function typeName(): string {
    return 'number';
  }

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
}
