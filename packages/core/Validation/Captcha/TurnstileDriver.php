<?php

namespace SchemableValidator\Validation\Captcha;

use SchemableValidator\Controllers\CurlController;

/**
 * Cloudflare Turnstile verification driver.
 *
 * The endpoint is hardcoded to the official Cloudflare siteverify URL.
 * All HTTP calls go through CurlController (HTTPS-only, no redirects, private-IP blocked).
 */
final class TurnstileDriver extends AbstractCaptchaDriver {
  private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

  /**
   * @param string      $secret           Turnstile secret key.
   * @param string|null $expectedHostname When provided, the hostname field in the verification
   *                                      response must match this value.  Prevents token replay
   *                                      from a phishing clone of the site.
   */
  public function __construct(string $secret, ?string $expectedHostname = null, ?CurlController $http = null) {
    if ($secret === '') {
      throw new \InvalidArgumentException('Turnstile secret must not be empty');
    }
    $this->secret           = $secret;
    $this->expectedHostname = $expectedHostname;
    $this->http             = $http;
  }

  protected function endpoint(): string {
    return self::ENDPOINT;
  }

  protected function providerName(): string {
    return 'Turnstile';
  }

  protected function buildParams(string $token): array {
    return [
      'secret'   => $this->secret,
      'response' => $token,
    ];
  }

  protected function postVerify(object $response, array $state, array $options): array {
    return $state;
  }
}
