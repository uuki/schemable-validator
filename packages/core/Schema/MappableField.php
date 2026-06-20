<?php

namespace SchemableValidator\Schema;

/**
 * Implemented by field schemas whose constraints can be expressed as a
 * neutral list of {rule, args} descriptors, consumed by BackendAdapter
 * implementations (e.g. RespectAdapter::compileDescriptors()).
 */
interface MappableField {
  /**
   * @return array<int, array{rule: string, args: array}>
   */
  public function toDescriptors(): array;
}
