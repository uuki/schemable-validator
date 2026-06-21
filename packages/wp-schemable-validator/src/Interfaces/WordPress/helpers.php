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

if (!function_exists('schv_register_code_fields')) {
  /**
   * Declare which field names a code-side SchemaBuilder defines for a given
   * schema slug. SchemaEditor uses this to warn about merge conflicts when
   * the same field name appears in both the GUI schema and the code schema.
   *
   * Call once at plugin load (e.g. in an init hook):
   *   schv_register_code_fields('merge-demo', ['company_name']);
   *
   * @param string   $schemaSlug  The slug used with schv_stored_schema().
   * @param string[] $fieldNames  Field names defined in the code-side SchemaBuilder.
   */
  function schv_register_code_fields(string $schemaSlug, array $fieldNames): void {
    $slug = sanitize_key($schemaSlug);
    $all  = get_option('schv_code_fields', []);
    if (!is_array($all)) {
      $all = [];
    }
    $all[$slug] = array_values(array_filter($fieldNames, 'is_string'));
    update_option('schv_code_fields', $all, false);
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
