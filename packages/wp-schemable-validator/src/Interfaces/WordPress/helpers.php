<?php

if (!function_exists('schv_message_dict')) {
  function schv_message_dict(): \SchemableValidator\I18n\MessageDict {
    return apply_filters('schv_message_dict', new \SchemableValidator\I18n\MessageDict());
  }
}

if (!function_exists('schv_validator')) {
  function schv_validator(array $schema, array $config = []): \SchemableValidator\Orchestration\Validator {
    if (!isset($config['dict'])) {
      $config['dict'] = schv_message_dict();
    }
    return new \SchemableValidator\Orchestration\Validator($schema, $config);
  }
}

if (!function_exists('schv_csrf')) {
  function schv_csrf(): \SchemableValidator\Security\CsrfGuard {
    return new \SchemableValidator\Security\CsrfGuard();
  }
}

if (!function_exists('schv_template')) {
  function schv_template(array $options = []): \SchemableValidator\Orchestration\Template {
    return new \SchemableValidator\Orchestration\Template($options);
  }
}

if (!function_exists('schv_form')) {
  function schv_form(): \SchemableValidator\Infrastructure\FormController {
    return new \SchemableValidator\Infrastructure\FormController();
  }
}

if (!function_exists('schv_stored_schema')) {
  function schv_stored_schema(string $slug): \SchemableValidator\Interfaces\WordPress\StoredSchemaProvider {
    return new \SchemableValidator\Interfaces\WordPress\StoredSchemaProvider($slug);
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
