<?php

namespace SchemableValidator\Schema;

use Respect\Validation\Validator as v;

final class RawRespectSchema extends AbstractFieldSchema implements UnmappableField {
  /** @var v */
  private $rule;

  public function __construct(v $rule) {
    $this->rule = $rule;
  }

  public function isMappable(): bool {
    return false;
  }

  public function toRespect(): v {
    return $this->rule;
  }

  public function toJsonSchema(): array {
    return [];
  }
}
