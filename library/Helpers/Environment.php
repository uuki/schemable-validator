<?php

namespace SchemableValidator\Helpers;

trait Environment {
  private function getEnvironment() {
    $result = 'unknown';

    if (defined('ABSPATH') && function_exists('wp')) {
      $result = 'wordpress';
    }
    return $result;
  }
}