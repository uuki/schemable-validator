<?php

namespace SchemableValidator\Validation;

/**
 * Swappable CAPTCHA verification driver.
 *
 * Built-in implementations: ReCaptchaV3Driver, HCaptchaDriver, TurnstileDriver.
 * Use NullCaptchaDriver in tests.
 */
interface CaptchaDriver {
  /**
   * Verify a CAPTCHA token submitted by the client.
   *
   * @param  string               $token   The raw token from the form POST.
   * @param  array<string, mixed> $options Provider-specific call-time options
   *                                       (e.g. ['action' => 'contact'] for reCAPTCHA v3).
   * @return array{is_valid: bool, score: float|null, errors: ?string}
   */
  public function verify(string $token, array $options = []): array;
}
