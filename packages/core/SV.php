<?php

namespace SchemableValidator;

use Respect\Validation\Validator as v;
use SchemableValidator\Schema\BooleanSchema;
use SchemableValidator\Schema\EnumSchema;
use SchemableValidator\Schema\FileSchema;
use SchemableValidator\Schema\IntegerSchema;
use SchemableValidator\Schema\NumberSchema;
use SchemableValidator\Schema\RawRespectSchema;
use SchemableValidator\Schema\StringSchema;

final class SV {
  public static function object(array $fields): SchemaBuilder {
    return new SchemaBuilder($fields);
  }

  public static function string(): StringSchema {
    return new StringSchema();
  }

  public static function integer(): IntegerSchema {
    return new IntegerSchema();
  }

  public static function number(): NumberSchema {
    return new NumberSchema();
  }

  public static function boolean(): BooleanSchema {
    return new BooleanSchema();
  }

  public static function enum(array $values): EnumSchema {
    return new EnumSchema($values);
  }

  public static function file(array $accept = []): FileSchema {
    return new FileSchema($accept);
  }

  /** Escape hatch: wrap an arbitrary Respect/Validation rule. */
  public static function respect(v $rule): RawRespectSchema {
    return new RawRespectSchema($rule);
  }
}
