<?php

namespace SchemableValidator;

use Respect\Validation\Validator as v;
use SchemableValidator\Contracts\SchemaProviderInterface;
use SchemableValidator\I18n\MessageDict;
use SchemableValidator\Schema\AbstractFieldSchema;
use SchemableValidator\Schema\FieldRef;
use SchemableValidator\Schema\WhenExpr;
use SchemableValidator\Validation\Adapters\RespectAdapter;

final class SchemaBuilder implements SchemaProviderInterface {
  /** @var array<string, AbstractFieldSchema> */
  private $fields;

  /**
   * Conditional rules.
   * @var array<array{field: string, expr: WhenExpr, require: string[]}>
   */
  private $conditionals = [];

  private ?MessageDict $messageDict = null;

  /** @param array<string, AbstractFieldSchema> $fields */
  public function __construct(array $fields) {
    foreach ($fields as $name => $field) {
      if (!($field instanceof AbstractFieldSchema)) {
        $given = is_object($field) ? get_class($field) : gettype($field);
        throw new \InvalidArgumentException(
          "SchemaBuilder field '{$name}' must be an AbstractFieldSchema instance, got {$given}. " .
          'Nested SV::object() values are not supported as field values.'
        );
      }
    }
    $this->fields = $fields;
  }

  /**
   * Add a conditional requirement.
   *
   * When the condition on $field is satisfied, $require fields become required.
   * $expr accepts:
   *   - A scalar (string/int/bool): treated as SV::equal($value) — field === value
   *   - SV::equal($value) or SV::notEqual($value), where $value is a scalar or SV::field('name')
   *
   * @param scalar|WhenExpr $expr
   * @param string[] $require
   * @return $this
   */
  public function when(string $field, $expr, array $require): self {
    if (!($expr instanceof WhenExpr)) {
      $expr = new WhenExpr('===', $expr);
    }
    $this->conditionals[] = ['field' => $field, 'expr' => $expr, 'require' => $require];
    return $this;
  }

  public function withMessages(MessageDict $dict): self {
    $this->messageDict = $dict;
    return $this;
  }

  /** Build a Validator from the schema, passing through optional Validator options. */
  public function toValidator(array $options = []): Validator {
    $schema = [];
    foreach ($this->fields as $name => $field) {
      $respect = RespectAdapter::compileField($field);
      // Optional fields: null or '' should always pass; non-empty values are validated normally.
      $schema[$name] = $field->isRequired() ? $respect : v::optional($respect);
    }
    $conditionals = $this->conditionals;
    return new Validator($schema, $options, $conditionals, $this->messageDict);
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

    // Conditional requirements → x-when (supports ===, !==, field refs)
    if (!empty($this->conditionals)) {
      $schema['x-when'] = array_map(function (array $cond): array {
        $expr    = $cond['expr'];
        $operand = $expr->operand;
        $entry   = ['field' => $cond['field'], 'op' => $expr->op, 'require' => $cond['require']];
        if ($operand instanceof FieldRef) {
          $entry['equalsField'] = $operand->name;
        } else {
          $entry['equals'] = $operand;
        }
        return $entry;
      }, $this->conditionals);

      // Also emit standard if/then (or allOf) for literal === conditions,
      // to remain compatible with JSON Schema validators.
      $literalEqual = array_values(array_filter(
        $this->conditionals,
        fn($c) => $c['expr']->op === '===' && !($c['expr']->operand instanceof FieldRef),
      ));
      if (count($literalEqual) === 1) {
        $cond = $literalEqual[0];
        $schema['if']   = ['properties' => [$cond['field'] => ['const' => $cond['expr']->operand]]];
        $schema['then'] = ['required' => $cond['require']];
      } elseif (count($literalEqual) > 1) {
        $schema['allOf'] = array_map(function (array $cond): array {
          return [
            'if'   => ['properties' => [$cond['field'] => ['const' => $cond['expr']->operand]]],
            'then' => ['required' => $cond['require']],
          ];
        }, $literalEqual);
      }
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
