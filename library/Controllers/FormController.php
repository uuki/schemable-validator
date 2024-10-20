<?php
namespace SchemableValidator\Controllers;

require_once __DIR__ . "/../constants.php";

session_start();

/**
 * Class FormController
 *
 * Manages form data validation and storage within the session.
 */
final class FormController {

  /**
   * Saves validated form data to the session.
   *
   * @param array<string, mixed> $data Array of validation results to be stored in the session. The data will be saved under the key SV_SESSION_VALIDATED_DATA.
   */
  public function save(array $data) {
    $_SESSION[SV_SESSION_VALIDATED_DATA] = $data;
  }

  /**
   * Retrieves validated form data from the session.
   *
   * @return array<string, mixed>|null The validation results from the session, or null if no data is found.
   */
  public function get() {
    return $_SESSION[SV_SESSION_VALIDATED_DATA] ?? null;
  }

  /**
   * Clears the validation results from the session.
   *
   * This method will remove the data stored under the key SV_SESSION_VALIDATED_DATA.
   */
  public function clear() {
    unset($_SESSION[SV_SESSION_VALIDATED_DATA]);
  }
}