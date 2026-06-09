<?php

namespace SchemableValidator\Helpers;

trait Security {
  private function sanitize(string $str, bool $stripNewlines = false): string {
    $result = strip_tags($str);
    $result = htmlspecialchars($result, ENT_QUOTES, 'UTF-8');
    if ($stripNewlines) {
      $result = preg_replace('/[\r\n\t]/', '', $result);
    }
    return trim($result);
  }
}
