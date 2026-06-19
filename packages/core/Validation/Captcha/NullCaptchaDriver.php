<?php

namespace SchemableValidator\Validation\Captcha;

use SchemableValidator\Validation\CaptchaDriver;

/**
 * No-op CAPTCHA driver for use in tests and local development.
 *
 * Passes by default. Construct with false to simulate a rejection.
 */
final class NullCaptchaDriver implements CaptchaDriver {
  private bool $passes;

  public function __construct(bool $passes = true) {
    $this->passes = $passes;
  }

  public function verify(string $token, array $options = []): array {
    return [
      'is_valid' => $this->passes,
      'score'    => null,
      'errors'   => $this->passes ? null : 'CAPTCHA rejected',
    ];
  }
}
