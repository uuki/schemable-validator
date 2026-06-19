<?php

namespace SchemableValidator\Validation\Captcha;

use SchemableValidator\Controllers\CurlController;
use SchemableValidator\Validation\CaptchaDriver;

/**
 * Cloudflare Turnstile verification driver.
 *
 * The endpoint is hardcoded to the official Cloudflare siteverify URL.
 * All HTTP calls go through CurlController (HTTPS-only, no redirects, private-IP blocked).
 */
final class TurnstileDriver implements CaptchaDriver {
  private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

  private string $secret;

  public function __construct(string $secret) {
    if ($secret === '') {
      throw new \InvalidArgumentException('Turnstile secret must not be empty');
    }
    $this->secret = $secret;
  }

  public function verify(string $token, array $options = []): array {
    $state = ['is_valid' => false, 'score' => null, 'errors' => null];

    try {
      $curl     = new CurlController();
      $result   = $curl->post(self::ENDPOINT, [
        'secret'   => $this->secret,
        'response' => $token,
      ]);
      $response = json_decode($result['response']);

      if (!isset($response->success)) {
        throw new \RuntimeException('malformed Turnstile response');
      }

      $state['is_valid'] = (bool) $response->success;

      if (!$state['is_valid'] && isset($response->{'error-codes'})) {
        // Log raw error codes for operators; do not expose to callers.
        error_log('schemable-validator: Turnstile errors: ' . implode(', ', (array) $response->{'error-codes'}));
      }
    } catch (\Exception $e) {
      error_log('schemable-validator: Turnstile verification failed: ' . $e->getMessage());
      $state['errors'] = 'CAPTCHA verification failed';
    }

    return $state;
  }
}
