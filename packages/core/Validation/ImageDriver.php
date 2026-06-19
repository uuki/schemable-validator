<?php

namespace SchemableValidator\Validation;

/**
 * Swappable image-constraint driver, called after FileValidationDriver
 * has accepted the file's MIME type.
 *
 * Built-in implementation: NativeImageDriver (uses getimagesize(), no external deps).
 */
interface ImageDriver {
  /**
   * Validate image-specific constraints (dimensions, file size) on an already
   * MIME-accepted upload.
   *
   * @param  array{name: string, type: string, tmp_name: string, error: int, size: int} $file
   * @param  array{maxWidth?: int, maxHeight?: int, minWidth?: int, minHeight?: int, maxSize?: int} $config
   * @return array{is_valid: bool, errors: ?string}
   */
  public function validate(array $file, array $config): array;
}
