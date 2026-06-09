<?php
namespace SchemableValidator\Controllers;

require_once __DIR__ . "/../constants.php";

/**
 * Class FormController
 *
 * Manages form data validation and storage within the session.
 */
final class FormController {

  private function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
  }

  /**
   * @param array<string, mixed> $data
   */
  public function save(array $data): void {
    $this->startSession();
    $_SESSION[SV_SESSION_VALIDATED_DATA] = $data;
  }

  /**
   * @return array<string, mixed>|null
   */
  public function get(): ?array {
    $this->startSession();
    return $_SESSION[SV_SESSION_VALIDATED_DATA] ?? null;
  }

  public function clear(): void {
    $this->startSession();
    unset($_SESSION[SV_SESSION_VALIDATED_DATA]);
  }
}
