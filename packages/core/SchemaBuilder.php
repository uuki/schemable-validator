<?php

namespace SchemableValidator;

use Respect\Validation\Validator as v;
use SchemableValidator\Contracts\SchemaProviderInterface;
use SchemableValidator\I18n\MessageDict;
use SchemableValidator\Schema\AbstractFieldSchema;
use SchemableValidator\Schema\FieldRef;
use SchemableValidator\Schema\WhenExpr;
use SchemableValidator\Validation\BackendAdapter;
use SchemableValidator\Validation\JsonLogicEval;
use SchemableValidator\Validation\Adapters\RespectAdapter;

final class SchemaBuilder implements SchemaProviderInterface {
  /** @var array<string, AbstractFieldSchema> */
  private $fields;

  /**
   * Conditional rules stored as JSONLogic conditions.
   * @var array<array{condition: array, require: string[]}>
   */
  private $conditionals = [];

  /** @var string[] */
  private $customFields = [];

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
    $lhs     = ['var' => $field];
    $operand = $expr->operand;
    $rhs     = ($operand instanceof FieldRef) ? ['var' => $operand->name] : $operand;
    $this->conditionals[] = [
      'condition' => [$expr->op => [$lhs, $rhs]],
      'require'   => $require,
    ];
    return $this;
  }

  /**
   * Declare server-side custom validation fields that have no JSON Schema equivalent.
   * Clients can inspect x-custom-fields and warn when .refine() is not wired up.
   *
   * @param string[] $names
   * @return $this
   */
  public function customFields(array $names): self {
    $this->customFields = array_values($names);
    return $this;
  }

  public function withMessages(MessageDict $dict): self {
    $this->messageDict = $dict;
    return $this;
  }

  /** Build a Validator from the schema, passing through optional Validator options. */
  public function toValidator(array $options = [], ?BackendAdapter $adapter = null): Validator {
    // Mappable fields are validated through the BackendAdapter via the JSON
    // Schema IR (engine-swappable). UnmappableField escape hatches (file/raw)
    // have no JSON Schema form, so they keep running on Respect `v` objects.
    $jsonSchema     = $this->toJsonSchema();
    $unmappable     = [];
    $transforms     = [];
    $inlineMessages = [];
    foreach ($this->fields as $name => $field) {
      if (!$field->isMappable()) {
        $respect          = RespectAdapter::compileField($field);
        $unmappable[$name] = $field->isRequired() ? $respect : v::optional($respect);
        $fieldMessages    = $field->getErrorMessages();
        if (!empty($fieldMessages)) {
          $inlineMessages[$name] = $fieldMessages;
        }
        continue;
      }
      $fieldTransforms = $field->getTransforms();
      if (!empty($fieldTransforms)) {
        $transforms[$name] = $fieldTransforms;
      }
    }
    $conditionals = $this->conditionals;
    return new Validator($unmappable, $options, $conditionals, $this->messageDict, $adapter, $transforms, $inlineMessages, $jsonSchema);
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

    if (!empty($this->customFields)) {
      $schema['x-custom-fields'] = $this->customFields;
    }

    if (!empty($unmapped)) {
      $schema['x-unmapped-fields'] = $unmapped;
    }

    // Conditional requirements → x-when (JSONLogic format)
    if (!empty($this->conditionals)) {
      $schema['x-when'] = array_map(function (array $cond): array {
        return ['condition' => $cond['condition'], 'require' => $cond['require']];
      }, $this->conditionals);

      // Also emit standard if/then (or allOf) for literal === conditions,
      // to remain compatible with JSON Schema validators that don't understand x-when.
      $literalEqual = array_values(array_filter(
        $this->conditionals,
        function (array $c): bool {
          $op  = (string) array_key_first($c['condition']);
          $rhs = $c['condition'][$op][1];
          return $op === '===' && !is_array($rhs);
        },
      ));
      if (count($literalEqual) === 1) {
        $cond  = $literalEqual[0];
        $field = $cond['condition']['==='][0]['var'];
        $value = $cond['condition']['==='][1];
        $schema['if']   = ['properties' => [$field => ['const' => $value]]];
        $schema['then'] = ['required' => $cond['require']];
      } elseif (count($literalEqual) > 1) {
        $schema['allOf'] = array_map(function (array $cond): array {
          $field = $cond['condition']['==='][0]['var'];
          $value = $cond['condition']['==='][1];
          return [
            'if'   => ['properties' => [$field => ['const' => $value]]],
            'then' => ['required' => $cond['require']],
          ];
        }, $literalEqual);
      }
    }

    return $schema;
  }

  /**
   * Export a JSON Forms / RJSF compatible UI Schema (companion document).
   * Does not affect validation — purely declarative layout metadata.
   */
  public function toUiSchema(): array {
    $elements = [];
    foreach ($this->fields as $name => $field) {
      if (!$field->isMappable()) {
        continue;
      }
      $elements[] = [
        'type'  => 'Control',
        'scope' => "#/properties/{$name}",
        'label' => $field->getLabel() ?? $name,
      ];
    }
    return [
      'type'     => 'VerticalLayout',
      'elements' => $elements,
    ];
  }

  public function toJson(): string {
    return json_encode(
      $this->toJsonSchema(),
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    );
  }
}
