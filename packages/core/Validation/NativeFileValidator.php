<?php

namespace SchemableValidator\Validation;

/**
 * Default, dependency-free file-validation driver. Checks the upload's real MIME
 * type (via finfo) against the field's allow-list — the same core logic the old
 * Respect FileExtension rule wrapped, with no Respect involvement.
 *
 * config: ['accept' => ['image/jpeg', 'application/pdf', ...]]. An empty/absent
 * allow-list accepts any successfully-uploaded file.
 */
final class NativeFileValidator implements FileValidationDriver {
  public function validate(array $file, array $config): array {
    $state = ['value' => $file, 'is_valid' => false, 'errors' => null];

    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
      $state['errors'] = 'file upload failed';
      return $state;
    }

    $accept = array_map('strtolower', $config['accept'] ?? []);
    if (empty($accept)) {
      $state['is_valid'] = true;
      return $state;
    }

    $tmpName = $file['tmp_name'] ?? '';
    $mimeType = '';
    if (is_string($tmpName) && $tmpName !== '' && is_file($tmpName)) {
      $finfo    = finfo_open(FILEINFO_MIME_TYPE);
      $mimeType = (string) finfo_file($finfo, $tmpName);
      finfo_close($finfo);
    }

    if (in_array(strtolower($mimeType), $accept, true)) {
      $state['is_valid'] = true;
    } else {
      $state['errors'] = 'file type is not allowed';
    }
    return $state;
  }
}
