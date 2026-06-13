<?php

namespace SchemableValidator\Schema;

use Respect\Validation\Validator as v;

final class StringSchema extends AbstractFieldSchema {
  /** @var int|null */
  private $min = null;

  /** @var int|null */
  private $max = null;

  /** @var array */
  private $extras = [];

  /** @return $this */
  public function min(int $n) {
    $this->min = $n;
    return $this;
  }

  /** @return $this */
  public function max(int $n) {
    $this->max = $n;
    return $this;
  }

  /** @return $this */
  public function email() {
    $this->extras[] = ['rule' => 'email', 'args' => []];
    return $this;
  }

  /** @return $this */
  public function url() {
    $this->extras[] = ['rule' => 'url', 'args' => []];
    return $this;
  }

  /** @return $this */
  public function pattern(string $p) {
    $this->extras[] = ['rule' => 'pattern', 'args' => [$p]];
    return $this;
  }

  /** @return $this */
  public function date() {
    $this->extras[] = ['rule' => 'date', 'args' => []];
    return $this;
  }

  /** @return $this */
  public function dateTime() {
    $this->extras[] = ['rule' => 'dateTime', 'args' => []];
    return $this;
  }

  /** @return $this */
  public function time() {
    $this->extras[] = ['rule' => 'time', 'args' => []];
    return $this;
  }

  /** @return $this */
  public function uuid() {
    $this->extras[] = ['rule' => 'uuid', 'args' => []];
    return $this;
  }

  /** @return $this */
  public function ipv4() {
    $this->extras[] = ['rule' => 'ipv4', 'args' => []];
    return $this;
  }

  /** @return $this */
  public function ipv6() {
    $this->extras[] = ['rule' => 'ipv6', 'args' => []];
    return $this;
  }

  /** @return $this */
  public function slug() {
    $this->extras[] = ['rule' => 'slug', 'args' => []];
    return $this;
  }

  /** @return $this */
  public function domain() {
    $this->extras[] = ['rule' => 'domain', 'args' => []];
    return $this;
  }

  public function toRespect(): v {
    $chain = v::create();
    $chain->addRule(RuleMapper::resolve('string', [])->respect);
    if ($this->min !== null || $this->max !== null) {
      $chain->addRule(RuleMapper::resolve('length', [$this->min, $this->max])->respect);
    }
    foreach ($this->extras as $extra) {
      $chain->addRule(RuleMapper::resolve($extra['rule'], $extra['args'])->respect);
    }
    return $chain;
  }

  public function toJsonSchema(): array {
    $schema = ['type' => 'string'];
    if ($this->min !== null) {
      $schema['minLength'] = $this->min;
    }
    if ($this->max !== null) {
      $schema['maxLength'] = $this->max;
    }
    foreach ($this->extras as $extra) {
      $mapping = RuleMapper::resolve($extra['rule'], $extra['args']);
      if ($mapping->isMappable()) {
        $schema = array_merge($schema, $mapping->jsonSchema);
      }
    }
    return $this->applyNullable($schema);
  }
}
