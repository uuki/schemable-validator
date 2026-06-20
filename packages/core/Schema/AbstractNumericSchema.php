<?php

namespace SchemableValidator\Schema;

/**
 * Shared base for IntegerSchema and NumberSchema.
 *
 * Holds the bounds array and the common toDescriptors() / toJsonSchema()
 * logic. Subclasses define the type name and keep their own min()/max()
 * methods (different type hints: int vs int|float).
 */
abstract class AbstractNumericSchema extends AbstractFieldSchema implements MappableField {
  /** @var array<int, array{rule: string, args: array}> */
  protected $bounds = [];

  /** Return the JSON Schema / descriptor type name ('integer' or 'number'). */
  abstract protected function typeName(): string;

  public function toDescriptors(): array {
    $descriptors = [['rule' => $this->typeName(), 'args' => []]];
    foreach ($this->bounds as $bound) {
      $descriptors[] = $bound;
    }
    return $descriptors;
  }

  public function toJsonSchema(): array {
    $schema = ['type' => $this->typeName()];
    foreach ($this->bounds as $bound) {
      $mapping = RuleMapper::resolve($bound['rule'], $bound['args']);
      $schema  = array_merge($schema, $mapping->jsonSchema);
    }
    return $this->applyXTransform($this->applyErrorMessages($this->applyNullable($schema)));
  }
}
