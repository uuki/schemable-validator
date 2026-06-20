<?php

namespace SchemableValidator\Adapters\Native;

use SchemableValidator\Validation\ImageDriver;

/**
 * Default image-constraint driver.
 *
 * Extension-spoofing defence uses two independent content-based checks:
 *   1. finfo_open(FILEINFO_MIME_TYPE) — reads magic bytes; the same mechanism
 *      NativeFileValidator uses.  Running it here too means NativeImageDriver
 *      is safe even when combined with a custom fileDriver that skips MIME checks.
 *   2. getimagesize() — parses the image header and yields pixel dimensions.
 *
 * File size is checked before either image read so oversized uploads are
 * rejected without touching the image data at all.
 * Neither check looks at $file['name'] (user-controlled) or $file['type']
 * (browser-supplied Content-Type, equally untrustworthy).
 */
final class NativeImageDriver implements ImageDriver {
  public function validate(array $file, array $config): array {
    $state   = ['is_valid' => false, 'errors' => null];
    $tmpPath = $file['tmp_name'] ?? '';

    if (!is_string($tmpPath) || $tmpPath === '' || !is_file($tmpPath)) {
      $state['errors'] = 'file not found';
      return $state;
    }

    // Check declared file size first — fast, avoids reading the file for rejects.
    if (isset($config['maxSize'])) {
      $size = filesize($tmpPath);
      if ($size === false || $size > (int) $config['maxSize']) {
        $state['errors'] = 'file exceeds maximum size';
        return $state;
      }
    }

    // Independent content-based MIME check (magic bytes, not extension).
    // Catches extension-spoofed uploads even when the preceding fileDriver
    // did not perform its own finfo check.
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $detected = $finfo !== false ? (string) finfo_file($finfo, $tmpPath) : '';
    if ($finfo !== false) {
      finfo_close($finfo);
    }
    if (strncmp($detected, 'image/', 6) !== 0) {
      $state['errors'] = 'file is not a valid image';
      return $state;
    }

    // getimagesize() parses the image header structure and returns dimensions.
    // Suppress the warning for malformed data; false return value handles it.
    $info = @getimagesize($tmpPath);
    if ($info === false) {
      $state['errors'] = 'file is not a valid image';
      return $state;
    }

    [$width, $height] = $info;

    if (isset($config['minWidth']) && $width < (int) $config['minWidth']) {
      $state['errors'] = sprintf('image width must be at least %d px', $config['minWidth']);
      return $state;
    }
    if (isset($config['maxWidth']) && $width > (int) $config['maxWidth']) {
      $state['errors'] = sprintf('image width must not exceed %d px', $config['maxWidth']);
      return $state;
    }
    if (isset($config['minHeight']) && $height < (int) $config['minHeight']) {
      $state['errors'] = sprintf('image height must be at least %d px', $config['minHeight']);
      return $state;
    }
    if (isset($config['maxHeight']) && $height > (int) $config['maxHeight']) {
      $state['errors'] = sprintf('image height must not exceed %d px', $config['maxHeight']);
      return $state;
    }

    $state['is_valid'] = true;
    return $state;
  }
}
