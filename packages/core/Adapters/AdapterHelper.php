<?php

namespace SchemableValidator\Adapters;

/**
 * Shared utilities for BackendAdapter implementations.
 */
final class AdapterHelper {
  /**
   * Extract per-field inline errorMessage maps from a JSON Schema's properties.
   *
   * @param  array<string, mixed> $jsonSchema  A JSON Schema object with 'properties'.
   * @return array<string, array<string, string>>  field => (keyword => message template)
   */
  public static function extractInlineMessages(array $jsonSchema): array {
    $inlineMessages = [];
    foreach ($jsonSchema['properties'] ?? [] as $name => $prop) {
      if (!empty($prop['errorMessage'])) {
        $inlineMessages[$name] = $prop['errorMessage'];
      }
    }
    return $inlineMessages;
  }
}
