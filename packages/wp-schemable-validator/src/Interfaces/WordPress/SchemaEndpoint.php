<?php

namespace SchemableValidator\Interfaces\WordPress;

use SchemableValidator\Contracts\SchemaProviderInterface;

final class SchemaEndpoint {
  /**
   * Register a REST endpoint that serves a JSON Schema.
   *
   * @param string                  $route    Route relative to /wp-json/schv/v1/ (e.g. '/schema/contact')
   * @param SchemaProviderInterface $provider Schema provider (e.g. a SchemaBuilder instance)
   */
  public static function register(string $route, SchemaProviderInterface $provider): void {
    add_action('rest_api_init', function () use ($route, $provider): void {
      register_rest_route('schv/v1', $route, [
        'methods'             => 'GET',
        'callback'            => function () use ($provider): \WP_REST_Response {
          $schema = $provider->toJsonSchema();
          $etag   = '"' . md5(serialize($schema)) . '"';

          // Schema is derived purely from PHP code — safe to cache aggressively.
          $response = new \WP_REST_Response($schema);
          $response->header('Cache-Control', 'public, max-age=3600');
          $response->header('ETag', $etag);

          // Conditional GET: return 304 if client already has current version.
          $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH'])
            ? trim($_SERVER['HTTP_IF_NONE_MATCH'])
            : '';
          if ($if_none_match === $etag) {
            $response->set_status(304);
            $response->set_data(null);
          }

          return $response;
        },
        'permission_callback' => '__return_true',
      ]);
    });
  }
}
