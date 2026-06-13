<?php

namespace SchemableValidator\Schema;

use Respect\Validation\Validator as v;

final class RuleMapping {
  /** @var v */
  public $respect;

  /** @var array|null */
  public $jsonSchema;

  public function __construct(v $respect, ?array $jsonSchema) {
    $this->respect    = $respect;
    $this->jsonSchema = $jsonSchema;
  }

  public function isMappable(): bool {
    return $this->jsonSchema !== null;
  }
}
