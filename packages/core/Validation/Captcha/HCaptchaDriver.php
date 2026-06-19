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
  private ?string $expectedHostname;

  /**
   * @param string      $secret           hCaptcha secret key.
   * @param string|null $siteKey          Optional site key — when provided, the response is
   *                                      tied to this site key by hCaptcha's backend.
   * @param string|null $expectedHostname When provided, the hostname field in the verification
   *                                      response must match this value.  Prevents token replay
   *                                      from a phishing clone of the site.
   */
  public function __construct(string $secret, ?string $siteKey = null, ?string $expectedHostname = null) {
    if ($secret === '') {
      throw new \InvalidArgumentException('hCaptcha secret must not be empty');
    }
    $this->secret           = $secret;
    $this->siteKey          = $siteKey;
    $this->expectedHostname = $expectedHostname;
  }

  public function verify(string $token, array $options = []): array {
    $state = ['is_valid' => false, 'score' => null, 'errors' => null];

    // Reject locally rather than forwarding an empty token to the provider.
    if ($token === '') {
      $state['errors'] = 'CAPTCHA token is missing';
      return $state;
    }

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

      // Verify hostname to prevent token replay from a phishing clone.
      if ($state['is_valid'] && $this->expectedHostname !== null) {
        if (!isset($response->hostname) || $response->hostname !== $this->expectedHostname) {
          $state['is_valid'] = false;
          error_log('schemable-validator: hCaptcha hostname mismatch');
        }
      }
    } catch (\Exception $e) {
      error_log('schemable-validator: hCaptcha verification failed: ' . $e->getMessage());
      $state['errors'] = 'CAPTCHA verification failed';
    }

    return $state;
  }
}
