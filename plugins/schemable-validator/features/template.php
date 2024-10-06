<?php
namespace SchemableValidator;

use SchemableValidator\FormController;
use SchemableValidator\Interface\Wordpress\Admin as wp_admin;

class Template {
  function __construct(array $aliases = []) {
    global $SV_INTERFACE_TARGET;
    $form_controller = new FormController();

    $this->admin = null;
    $this->data = $form_controller->get();
    $this->aliases = array_merge([
      'name' => 'name',
      'email' => 'email',
      'body' => 'body'
    ], $aliases);

    if ($SV_INTERFACE_TARGET === 'wordpress') {
      $this->admin = new wp_admin();
    }
  }

  function get() {
    $format = $this->admin->get_template();
    $body = $format;

    foreach ($this->aliases as $key => $value) {
      $field = $this->data[$this->aliases[$value]] ?? null;
      if (isset($field)) {
        $body = str_replace('{'.$key.'}', $field['value'], $body);
      }
    }
    return $body;
  }
}
