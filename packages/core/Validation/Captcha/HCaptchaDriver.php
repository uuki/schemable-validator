<?php

namespace SchemableValidator\Validation\Captcha;

use SchemableValidator\Controllers\CurlController;

/**
 * hCaptcha verification driver.
 *
 * The endpoint is hardcoded to the official hCaptcha siteverify URL.
 * All HTTP calls go through CurlController (HTTPS-only, no redirects, private-IP blocked).
 */
final class HCaptchaDriver extends AbstractCaptchaDriver {
  private const ENDPOINT = 'https://hcaptcha.com/siteverify';

  private ?string $siteKey;

  /**
   * @param string      $secret           hCaptcha secret key.
   * @param string|null $siteKey          Optional site key — when provided, the response is
   *                                      tied to this site key by hCaptcha's backend.
   * @param string|null $expectedHostname When provided, the hostname field in the verification
   *                                      response must match this value.  Prevents token replay
   *                                      from a phishing clone of the site.
   */
  public function __construct(string $secret, ?string $siteKey = null, ?string $expectedHostname = null, ?CurlController $http = null) {
    if ($secret === '') {
      throw new \InvalidArgumentException('hCaptcha secret must not be empty');
    }
    $this->secret           = $secret;
    $this->siteKey          = $siteKey;
    $this->expectedHostname = $expectedHostname;
    $this->http             = $http;
  }

  protected function endpoint(): string {
    return self::ENDPOINT;
  }

  protected function providerName(): string {
    return 'hCaptcha';
  }

  protected function buildParams(string $token): array {
    $params = [
      'secret'   => $this->secret,
      'response' => $token,
    ];
    if ($this->siteKey !== null) {
      $params['sitekey'] = $this->siteKey;
    }
    return $params;
  }

  protected function postVerify(object $response, array $state, array $options): array {
    return $state;
  }
}
