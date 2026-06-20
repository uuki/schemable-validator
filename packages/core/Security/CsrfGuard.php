<?php

namespace SchemableValidator\Security;

/**
 * Session-backed CSRF token manager with per-form scoping and single-use semantics.
 *
 * Tokens are stored in $_SESSION['schv_csrf_tokens'][$form] with a 1-hour expiry.
 * Each token is consumed on first successful verification to prevent replay.
 */
final class CsrfGuard {
  private static bool $sessionStarted = false;

  /**
   * Generate a CSRF token scoped to a specific form and store it with a 1-hour expiry.
   *
   * @param string $form Unique identifier for the form (e.g. 'contact', 'login').
   */
  public function createToken(string $form = 'default'): string {
    $token = bin2hex(random_bytes(32));
    $this->startSession();

    if (!isset($_SESSION['schv_csrf_tokens']) || !is_array($_SESSION['schv_csrf_tokens'])) {
      $_SESSION['schv_csrf_tokens'] = [];
    }
    $_SESSION['schv_csrf_tokens'][$form] = [
      'token' => $token,
      'exp'   => time() + 3600,
    ];

    return $token;
  }

  /**
   * Verify a CSRF token for the given form scope.
   * Returns false if the token is missing, expired, or does not match.
   * The token is consumed on first successful use (single-use).
   *
   * @param string $form Must match the $form used in createToken().
   */
  public function checkToken(string $token, string $form = 'default'): bool {
    $this->startSession();
    $stored = $_SESSION['schv_csrf_tokens'][$form] ?? null;

    if (!is_array($stored) || !isset($stored['token'], $stored['exp'])) {
      return false;
    }
    if (time() > $stored['exp']) {
      unset($_SESSION['schv_csrf_tokens'][$form]);
      return false;
    }
    $valid = hash_equals($stored['token'], $token);
    if ($valid) {
      unset($_SESSION['schv_csrf_tokens'][$form]);
    }
    return $valid;
  }

  private function startSession(): void {
    if (!self::$sessionStarted && session_status() !== PHP_SESSION_ACTIVE) {
      $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
      session_set_cookie_params([
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
      ]);
      session_start();
      self::$sessionStarted = true;
    }
  }
}
