<?php

if (!function_exists('schv_validator')) {
  function schv_validator(array $schema, array $options = []): \SchemableValidator\Validator {
    return new \SchemableValidator\Validator($schema, $options);
  }
}

if (!function_exists('schv_template')) {
  function schv_template(array $options = []): \SchemableValidator\Template {
    return new \SchemableValidator\Template($options);
  }
}

if (!function_exists('schv_form')) {
  function schv_form(): \SchemableValidator\Controllers\FormController {
    return new \SchemableValidator\Controllers\FormController();
  }
}
