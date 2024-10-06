<?php
namespace SchemableValidator\Interface\Wordpress;

require_once SV_ROOT_DIR . '/interfaces/wordpress/constants.php';
require_once SV_ROOT_DIR . '/interfaces/wordpress/edit-body.php';

class Admin {
  function get_template() {
    return get_option(SV_REPLY_FORMAT_FOR_USER);
  }
}