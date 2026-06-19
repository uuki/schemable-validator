<?php

namespace SchemableValidator\Validation;

/**
 * Driver port for file-upload validation — a capability that has no JSON Schema
 * (IR) representation. The core ships a dependency-free NativeFileValidator;
 * users may inject their own (size, dimensions, antivirus, an engine-backed
 * rule, ...) so the core stays decoupled from any external validator.
 */
interface FileValidationDriver {
  /**
   * @param array<string, mixed> $file   Normalized upload: {name,type,tmp_name,error,size}.
   * @param array<string, mixed> $config Field config, e.g. ['accept' => ['image/jpeg', ...]].
   * @return array{value: mixed, is_valid: bool, errors: ?string}
   */
  public function validate(array $file, array $config): array;
}
