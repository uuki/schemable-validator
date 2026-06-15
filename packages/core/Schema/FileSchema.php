<?php

namespace SchemableValidator\Schema;

use Respect\Validation\Validator as v;
use SchemableValidator\Rules\FileExtension;

final class FileSchema extends AbstractFieldSchema implements UnmappableField {
  /** @var array */
  private $accept;

  public function __construct(array $accept = []) {
    $this->accept = $accept;
  }

  public function isMappable(): bool {
    return false;
  }

  public function toRespect(): v {
    $chain = v::create();
    $chain->addRule(new FileExtension($this->accept));
    return $chain;
  }

  public function toJsonSchema(): array {
    return [];
  }
}
