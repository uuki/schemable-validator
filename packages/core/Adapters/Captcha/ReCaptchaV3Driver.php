<?php

namespace SchemableValidator\Adapters\Captcha;

use SchemableValidator\Infrastructure\CurlController;

/**
 * Google reCAPTCHA v3 driver.
 *
 * The verification endpoint is restricted to the two official Google domains;
 * arbitrary URLs cannot be injected. All HTTP calls go through CurlController,
 * which enforces HTTPS, disables redirects, and blocks private/reserved IPs.
 */
final class ReCaptchaV3Driver extends AbstractCaptchaDriver {
  private const ALLOWED_ENDPOINTS = [
    'https://www.google.com/recaptcha/api/siteverify',
    'https://www.recaptcha.net/recaptcha/api/siteverify',
  ];

  private float $minScore;
  private string $endpoint;

  public function __construct(
    string $secret,
    float $minScore = 0.5,
    string $endpoint = 'https://www.google.com/recaptcha/api/siteverify',
    ?CurlController $http = null
  ) {
    if ($secret === '') {
      throw new \InvalidArgumentException('reCAPTCHA secret must not be empty');
    }
    if (!in_array($endpoint, self::ALLOWED_ENDPOINTS, true)) {
      throw new \InvalidArgumentException(
        'endpoint must be one of the official Google reCAPTCHA siteverify URLs'
      );
    }
    $this->secret           = $secret;
    $this->minScore         = $minScore;
    $this->endpoint         = $endpoint;
    $this->expectedHostname = null;
    $this->http             = $http;
  }

  protected function endpoint(): string {
    return $this->endpoint;
  }

  protected function providerName(): string {
    return 'reCAPTCHA';
  }

  protected function buildParams(string $token): array {
    return [
      'secret'   => $this->secret,
      'response' => $token,
    ];
  }

  protected function postVerify(object $response, array $state, array $options): array {
    if ($state['is_valid'] && isset($response->score)) {
      $state['score']    = (float) $response->score;
      $state['is_valid'] = $response->score >= $this->minScore;
    }

    // When the caller specifies an expected action, require it to be present
    // and matching in the response.  A missing action field means the token
    // was not generated for the correct interaction — treat as invalid.
    if (!empty($options['action'])) {
      if (!isset($response->action) || $options['action'] !== $response->action) {
        $state['is_valid'] = false;
      }
    }

    return $state;
  }
}
