<?php

namespace SchemableValidator\Interfaces\WordPress;

use SchemableValidator\Contracts\SchemaProviderInterface;

/**
 * Serves a JSON Schema stored in wp_options.
 *
 * Pair with SchemaEditor (admin UI) to let site operators define
 * validation schemas without writing PHP.
 */
final class StoredSchemaProvider implements SchemaProviderInterface {
  private string $slug;

  public function __construct(string $slug) {
    $this->slug = $slug;
  }

  public function optionKey(): string {
    return 'schv_schema_' . $this->slug;
  }

  public function toJsonSchema(): array {
    $stored = get_option($this->optionKey(), null);
    if (!is_array($stored) || empty($stored['properties'])) {
      return ['type' => 'object', 'properties' => (object) [], 'required' => []];
    }
    return $stored;
  }

  public function toJson(): string {
    return (string) json_encode(
      $this->toJsonSchema(),
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
  }
}
