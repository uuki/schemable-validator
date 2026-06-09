<?php
/**
 * Plugin Name: Schemable Validator
 * Description: Schema based validation plugin.
 */

require_once __DIR__ . '/vendor/autoload.php';

use SchemableValidator\Interfaces\WordPress\Plugin;
new Plugin();

if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local') {
  require_once __DIR__ . '/examples/loader.php';
}
