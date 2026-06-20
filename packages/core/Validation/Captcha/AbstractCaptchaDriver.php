<?php

namespace SchemableValidator\Validation\Captcha;

use SchemableValidator\Controllers\CurlController;
use SchemableValidator\Validation\CaptchaDriver;

/**
 * Common CAPTCHA verification logic shared by network-backed drivers
 * (reCAPTCHA v3, hCaptcha, Turnstile).
 *
 * Subclasses provide:
 *  - endpoint():    verification URL (hardcoded per provider)
 *  - buildParams(): POST parameters for the verification request
 *  - postVerify():  provider-specific response checks (score, action, hostname)
 *
 * NullCaptchaDriver does NOT extend this class because it makes no
 * network call and implements CaptchaDriver directly.
 */
abstract class AbstractCaptchaDriver implements CaptchaDriver {

  /** @var string CAPTCHA secret key. */
  protected string $secret;

  /** @var string|null Expected hostname for token-replay prevention. */
  protected ?string $expectedHostname;

  /** @var CurlController|null Injected HTTP client (null = create on demand). */
  protected ?CurlController $http = null;

  /**
   * Return the verification endpoint URL (hardcoded per provider).
   */
  abstract protected function endpoint(): string;

  /**
   * Build POST parameters for the verification request.
   *
   * @return array<string, string>
   */
  abstract protected function buildParams(string $token): array;

  /**
   * Provider-specific post-verification checks.
   *
   * Called after the base success check passes. Implementations may
   * inspect the score (reCAPTCHA v3), log error codes, or verify
   * hostname fields. Return the (possibly modified) state array.
   *
   * @param  object               $response Decoded JSON response from the provider.
   * @param  array<string, mixed> $state    Current verification state.
   * @param  array<string, mixed> $options  Caller-supplied options.
   * @return array{is_valid: bool, score: float|null, errors: ?string}
   */
  abstract protected function postVerify(object $response, array $state, array $options): array;

  /**
   * Return the provider name for log messages (e.g. "reCAPTCHA", "hCaptcha").
   */
  abstract protected function providerName(): string;

  public function verify(string $token, array $options = []): array {
    $state = ['is_valid' => false, 'score' => null, 'errors' => null];

    // Reject locally rather than forwarding an empty token to the provider.
    if ($token === '') {
      $state['errors'] = 'CAPTCHA token is missing';
      return $state;
    }

    try {
      $curl     = $this->http ?? new CurlController();
      $result   = $curl->post($this->endpoint(), $this->buildParams($token));
      $response = json_decode($result['response']);

      if (!isset($response->success)) {
        throw new \RuntimeException('malformed ' . $this->providerName() . ' response');
      }

      $state['is_valid'] = (bool) $response->success;

      // Log error codes from the provider for operators.
      if (!$state['is_valid'] && isset($response->{'error-codes'})) {
        error_log('schemable-validator: ' . $this->providerName() . ' errors: ' . implode(', ', (array) $response->{'error-codes'}));
      }

      // Verify hostname to prevent token replay from a phishing clone.
      if ($state['is_valid'] && $this->expectedHostname !== null) {
        if (!isset($response->hostname) || $response->hostname !== $this->expectedHostname) {
          $state['is_valid'] = false;
          error_log('schemable-validator: ' . $this->providerName() . ' hostname mismatch');
        }
      }

      // Provider-specific post-verification checks.
      $state = $this->postVerify($response, $state, $options);
    } catch (\Exception $e) {
      error_log('schemable-validator: ' . $this->providerName() . ' verification failed: ' . $e->getMessage());
      $state['errors'] = 'CAPTCHA verification failed';
    }

    return $state;
  }
}
