<?php
namespace SchemableValidator;

session_start();
class FormController {
  public function save(array $data) {
    $_SESSION[SV_SESSION_VALIDATED_DATA] = $data;
  }

  public function get() {
    return $_SESSION[SV_SESSION_VALIDATED_DATA] ?? null;
  }

  public function clear() {
    unset($_SESSION[SV_SESSION_VALIDATED_DATA]);
  }
}