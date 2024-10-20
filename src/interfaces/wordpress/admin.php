<?php
namespace SchemableValidator\Interface\Wordpress;

require_once SV_ROOT_DIR . '/interfaces/wordpress/constants.php';
require_once SV_ROOT_DIR . '/interfaces/wordpress/edit_body.php';

class Admin {

  function __construct($options) {
    $this->templates = array_merge([
      'admin' => get_option(SV_REPLY_FORMAT_FOR_ADMIN),
      'user' => get_option(SV_REPLY_FORMAT_FOR_USER),
    ], isset($options['templates']) ? $options['templates'] : []);
  }
  function get_template(string $template_name) {
    return $this->templates[$template_name] ?: '';
  }
}