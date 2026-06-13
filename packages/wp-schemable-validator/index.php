<?php
/**
 * Plugin Name: Schemable Validator
 * Description: Schema based validation plugin.
 */

require_once __DIR__ . '/vendor/autoload.php';

use SchemableValidator\Interfaces\WordPress\Plugin;
new Plugin();

if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local') {
  // WP Playground: each PHP worker has its own in-memory /home/web_user,
  // so sessions stored there are not shared across workers. Redirect to a
  // NodeFS-backed path under wp-content that IS shared across all workers.
  if (ini_get('session.save_path') === '/home/web_user') {
    $schv_sessions = '/wordpress/wp-content/schv-sessions';
    @mkdir($schv_sessions, 0700, true);
    ini_set('session.save_path', $schv_sessions);
  }

  require_once __DIR__ . '/examples/loader.php';
  if (!is_admin() && !get_option('schv_setup_done')) {
    require_once __DIR__ . '/setup.php';
  }
}
