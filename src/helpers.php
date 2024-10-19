<?php 

if (!function_exists('sv_get_environment')) {
  function sv_get_environment() {
    $result = 'unknown';

    if (defined('ABSPATH') && function_exists('wp')) {
      $result = 'wordpress';
    }
    return $result;
  }
}