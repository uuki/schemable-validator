<?php

namespace SchemableValidator\Validation\Captcha;

use SchemableValidator\Controllers\CurlController;
use SchemableValidator\Validation\CaptchaDriver;

/**
 * hCaptcha verification driver.
 *
 * The endpoint is hardcoded to the official hCaptcha siteverify URL.
 * All HTTP calls go through CurlController (HTTPS-only, no redirects, private-IP blocked).
 */
final class HCaptchaDriver implements CaptchaDriver {
  private const ENDPOINT = 'https://hcaptcha.com/siteverify';

  private string $secret;
  private ?string $siteKey;

  /**
   * @param string      $secret  hCaptcha secret key.
   * @param string|null $siteKey Optional site key — when provided, the response is
   *                             tied to this site key by hCaptcha's backend.
   */
  public function __construct(string $secret, ?string $siteKey = null) {
    if ($secret === '') {
      throw new \InvalidArgumentException('hCaptcha secret must not be empty');
    }
    $this->secret  = $secret;
    $this->siteKey = $siteKey;
  }

  public function verify(string $token, array $options = []): array {
    $state = ['is_valid' => false, 'score' => null, 'errors' => null];

    try {
      $params = [
        'secret'   => $this->secret,
        'response' => $token,
      ];
      if ($this->siteKey !== null) {
        $params['sitekey'] = $this->siteKey;
      }

      $curl     = new CurlController();
      $result   = $curl->post(self::ENDPOINT, $params);
      $response = json_decode($result['response']);

      if (!isset($response->success)) {
        throw new \RuntimeException('malformed hCaptcha response');
      }

      $state['is_valid'] = (bool) $response->success;

      if (!$state['is_valid'] && isset($response->{'error-codes'})) {
        // Log raw error codes for operators; do not expose to callers.
        error_log('schemable-validator: hCaptcha errors: ' . implode(', ', (array) $response->{'error-codes'}));
      }
    } catch (\Exception $e) {
      error_log('schemable-validator: hCaptcha verification failed: ' . $e->getMessage());
      $state['errors'] = 'CAPTCHA verification failed';
    }

    return $state;
  }
}
