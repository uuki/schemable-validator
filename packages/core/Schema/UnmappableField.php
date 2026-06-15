<?php

namespace SchemableValidator\Schema;

use Respect\Validation\Validator as v;

/**
 * Implemented by field schemas with no JSON Schema equivalent (escape
 * hatches: FileSchema, RawRespectSchema). Adapters execute these directly
 * via their native validator instead of {rule, args} descriptors.
 */
interface UnmappableField {
  public function toRespect(): v;
}
