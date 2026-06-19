<?php

namespace SchemableValidator\Validation\Captcha;

use SchemableValidator\Controllers\CurlController;
use SchemableValidator\Validation\CaptchaDriver;

/**
 * Google reCAPTCHA v3 driver.
 *
 * The verification endpoint is restricted to the two official Google domains;
 * arbitrary URLs cannot be injected. All HTTP calls go through CurlController,
 * which enforces HTTPS, disables redirects, and blocks private/reserved IPs.
 */
final class ReCaptchaV3Driver implements CaptchaDriver {
  private const ALLOWED_ENDPOINTS = [
    'https://www.google.com/recaptcha/api/siteverify',
    'https://www.recaptcha.net/recaptcha/api/siteverify',
  ];

  private string $secret;
  private float $minScore;
  private string $endpoint;

  public function __construct(
    string $secret,
    float $minScore = 0.5,
    string $endpoint = 'https://www.google.com/recaptcha/api/siteverify'
  ) {
    if ($secret === '') {
      throw new \InvalidArgumentException('reCAPTCHA secret must not be empty');
    }
    if (!in_array($endpoint, self::ALLOWED_ENDPOINTS, true)) {
      throw new \InvalidArgumentException(
        'endpoint must be one of the official Google reCAPTCHA siteverify URLs'
      );
    }
    $this->secret   = $secret;
    $this->minScore = $minScore;
    $this->endpoint = $endpoint;
  }

  public function verify(string $token, array $options = []): array {
    $state = ['is_valid' => false, 'score' => null, 'errors' => null];

    // Reject locally rather than forwarding an empty token to the provider.
    if ($token === '') {
      $state['errors'] = 'CAPTCHA token is missing';
      return $state;
    }

    try {
      $curl   = new CurlController();
      $result = $curl->post($this->endpoint, [
        'secret'   => $this->secret,
        'response' => $token,
      ]);

      $response = json_decode($result['response']);

      if (!isset($response->success)) {
        throw new \RuntimeException('malformed reCAPTCHA response');
      }

      $state['is_valid'] = (bool) $response->success;

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
    } catch (\Exception $e) {
      error_log('schemable-validator: reCAPTCHA verification failed: ' . $e->getMessage());
      $state['errors'] = 'CAPTCHA verification failed';
    }

    return $state;
  }
}
