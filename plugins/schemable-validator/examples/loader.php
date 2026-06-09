<?php
if (!defined('ABSPATH')) {
  return;
}

foreach (['validate', 'files', 'csrf', 'template', 'multipage'] as $example) {
  require_once __DIR__ . "/{$example}.php";
}
