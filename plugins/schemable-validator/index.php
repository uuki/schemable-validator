<?php
/**
 * Plugin Name: schemable-validator
 * Plugin URI: None
 * Description: Respect/Validation based, schema-based validation library
 * Version: 0.1.0
 * Author: uuki<uuki.dev@gmail.com>
 */

require_once "constants.php";

require_once SV_ROOT_DIR . "/features/rules/index.php";
require_once SV_ROOT_DIR . "/features/core.php";
require_once SV_ROOT_DIR . "/features/template.php";
require_once SV_ROOT_DIR . "/controllers/form.php";

$SV_INTERFACE_TARGET = null;

if (defined('ABSPATH') && function_exists('wp')) {
  $SV_INTERFACE_TARGET = 'wordpress';
  require_once SV_ROOT_DIR . "/interfaces/wordpress/admin.php";
}
