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

if (!function_exists('schv_register_schema')) {
  function schv_register_schema(string $route, \SchemableValidator\Contracts\SchemaProviderInterface $provider): void {
    \SchemableValidator\Interfaces\WordPress\SchemaEndpoint::register($route, $provider);
  }
}

if (!function_exists('schv_schema_url')) {
  /**
   * Return the absolute REST URL for a registered schema endpoint.
   * Pass the same $route string used in schv_register_schema().
   *
   * Example: schv_schema_url('/contact') → https://example.com/wp-json/schv/v1/contact
   */
  function schv_schema_url(string $route): string {
    return get_rest_url(null, 'schv/v1' . $route);
  }
}
