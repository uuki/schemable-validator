<?php

namespace SchemableValidator\Contracts;

interface SchemaProviderInterface {
  public function toJsonSchema(): array;
}
