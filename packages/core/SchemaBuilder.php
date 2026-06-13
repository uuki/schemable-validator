<?php

namespace SchemableValidator;

use SchemableValidator\Contracts\SchemaProviderInterface;
use SchemableValidator\Schema\AbstractFieldSchema;

final class SchemaBuilder implements SchemaProviderInterface {
  /** @var array<string, AbstractFieldSchema> */
  private $fields;

  /** @param array<string, AbstractFieldSchema> $fields */
  public function __construct(array $fields) {
    $this->fields = $fields;
  }

  /** Build a Validator from the schema, passing through optional Validator options. */
  public function toValidator(array $options = []): Validator {
    $schema = [];
    foreach ($this->fields as $name => $field) {
      $schema[$name] = $field->toRespect();
    }
    return new Validator($schema, $options);
  }

  /**
   * Export the schema as a JSON Schema (draft 2020-12) array.
   * Fields where isMappable() === false are excluded and listed in x-unmapped-fields.
   */
  public function toJsonSchema(): array {
    $properties = [];
    $required   = [];
    $unmapped   = [];

    foreach ($this->fields as $name => $field) {
      if (!$field->isMappable()) {
        $unmapped[] = $name;
        continue;
      }
      $properties[$name] = $field->toJsonSchema();
      if ($field->isRequired()) {
        $required[] = $name;
      }
    }

    $schema = [
      '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
      'type'       => 'object',
      'properties' => $properties,
    ];

    if (!empty($required)) {
      $schema['required'] = $required;
    }

    if (!empty($unmapped)) {
      $schema['x-unmapped-fields'] = $unmapped;
    }

    return $schema;
  }

  public function toJson(): string {
    return json_encode(
      $this->toJsonSchema(),
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    );
  }
}
