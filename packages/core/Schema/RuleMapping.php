<?php

namespace SchemableValidator\Schema;

final class RuleMapping {
  /** @var string */
  public $rule;

  /** @var array */
  public $args;

  /** @var array|null */
  public $jsonSchema;

  public function __construct(string $rule, array $args, ?array $jsonSchema) {
    $this->rule       = $rule;
    $this->args       = $args;
    $this->jsonSchema = $jsonSchema;
  }

  public function isMappable(): bool {
    return $this->jsonSchema !== null;
  }
}
