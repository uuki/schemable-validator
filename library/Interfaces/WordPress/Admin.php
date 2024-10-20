<?php
namespace SchemableValidator\Interfaces\Wordpress;

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/EditBody.php';

final class Admin {

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