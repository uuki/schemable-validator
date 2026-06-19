<?php
/**
 * Plugin Name: Schemable Validator
 * Description: Schema based validation plugin.
 * Version: 0.12.3
 * Requires at least: 5.9
 * Requires PHP: 7.4
 */

// ── Activation / deactivation ─────────────────────────────────────────────────

register_activation_hook(__FILE__, function (): void {
  if (version_compare(PHP_VERSION, '7.4', '<')) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die(
      esc_html('Schemable Validator requires PHP 7.4 or higher. Your server is running PHP ' . PHP_VERSION . '.'),
      esc_html('Plugin Activation Error'),
      ['back_link' => true]
    );
  }

  global $wp_version;
  if (version_compare($wp_version, '5.9', '<')) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die(
      esc_html('Schemable Validator requires WordPress 5.9 or higher. Your site is running WordPress ' . $wp_version . '.'),
      esc_html('Plugin Activation Error'),
      ['back_link' => true]
    );
  }

  flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function (): void {
  flush_rewrite_rules();
});

// ── Runtime requirement checks ────────────────────────────────────────────────
// Guard against WP/PHP being downgraded after the plugin was activated.

if (version_compare(PHP_VERSION, '7.4', '<')) {
  add_action('admin_notices', function (): void {
    echo '<div class="notice notice-error"><p>'
      . esc_html('Schemable Validator has been disabled: PHP 7.4 or higher is required (current: ' . PHP_VERSION . ').')
      . '</p></div>';
  });
  return;
}

global $wp_version;
if (version_compare($wp_version, '5.9', '<')) {
  add_action('admin_notices', function () use ($wp_version): void {
    echo '<div class="notice notice-error"><p>'
      . esc_html('Schemable Validator has been disabled: WordPress 5.9 or higher is required (current: ' . $wp_version . ').')
      . '</p></div>';
  });
  return;
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────

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
