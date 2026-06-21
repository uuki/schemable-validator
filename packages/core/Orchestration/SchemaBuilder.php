<?php

namespace SchemableValidator\Orchestration;

use SchemableValidator\Contracts\SchemaProviderInterface;
use SchemableValidator\I18n\MessageDict;
use SchemableValidator\Schema\AbstractFieldSchema;
use SchemableValidator\Schema\FieldRef;
use SchemableValidator\Schema\FileSchema;
use SchemableValidator\Schema\WhenExpr;
use SchemableValidator\Validation\BackendAdapter;
use SchemableValidator\Validation\CaptchaDriver;
use SchemableValidator\Validation\CustomField;
use SchemableValidator\Validation\FileValidationDriver;
use SchemableValidator\Validation\ImageDriver;
use SchemableValidator\Validation\JsonLogicEval;

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

  /** @var array<string, mixed>|null External JSON Schema to merge (e.g. from StoredSchemaProvider). */
  private ?array $mergedJsonSchema = null;

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

  /**
   * Merge an external JSON Schema (e.g. from StoredSchemaProvider / GUI editor)
   * with this builder's fields.
   *
   * The external schema supplies primitive fields that the GUI can express.
   * The builder supplies fields that require code: file uploads, custom
   * validators, cross-field conditions, and driver injection.
   *
   * On conflict (same field name in both), the builder's definition wins.
   *
   * @param array<string, mixed> $jsonSchema  A JSON Schema 2020-12 object
   *                                          with at least `properties`.
   * @return $this
   */
  public function mergeJsonSchema(array $jsonSchema): self {
    $this->mergedJsonSchema = $jsonSchema;
    return $this;
  }

  public function withMessages(MessageDict $dict): self {
    $this->messageDict = $dict;
    return $this;
  }

  /**
   * Build a Validator from the schema.
   *
   * @param array $config  Engine config:
   *                       'adapter'       => BackendAdapter      (default: NativeAdapter)
   *                       'fileDriver'    => FileValidationDriver (default: NativeFileValidator)
   *                       'imageDriver'   => ImageDriver          (default: null — skips image checks)
   *                       'captchaDriver' => CaptchaDriver        (default: null — captcha unavailable)
   */
  public function toValidator(array $config = []): Validator {
    $jsonSchema     = $this->toJsonSchema(['includeServerOnly' => true]);
    $customFields   = [];
    $transforms     = [];
    $fileConfigs    = [];
    foreach ($this->fields as $name => $field) {
      // File fields → dependency-free FileValidationDriver (handled by validateFiles()).
      if ($field instanceof FileSchema) {
        $fileConfigs[$name] = ['accept' => $field->getAccept()];
        $imageConstraints = $field->getImageConstraints();
        if (!empty($imageConstraints)) {
          $fileConfigs[$name]['image'] = $imageConstraints;
        }
        continue;
      }
      // (B) escape hatches → engine-agnostic CustomField::evaluate().
      if ($field instanceof CustomField) {
        $customFields[$name] = $field;
        continue;
      }
      $fieldTransforms = $field->getTransforms();
      if (!empty($fieldTransforms)) {
        $transforms[$name] = $fieldTransforms;
      }
    }
    return new Validator($jsonSchema, array_merge($config, [
      'conditionals' => $this->conditionals,
      'dict'         => $this->messageDict,
      'transforms'   => $transforms,
      'fileConfigs'  => $fileConfigs,
      'customFields' => $customFields,
    ]));
  }

  /** URI of the schemable meta-schema extending draft 2020-12 with x-* keywords. */
  public const META_SCHEMA_URI = 'https://schemable-validator.dev/schema/2026-06/schemable.json';

  /**
   * Export the schema as a JSON Schema (draft 2020-12) array.
   * Fields where isMappable() === false are excluded and listed in x-unmapped-fields.
   *
   * @param array{metaSchema?: bool, includeServerOnly?: bool} $options
   *   metaSchema: when true, $schema points to the schemable meta-schema URI
   *     (IDE completion + no unknown-property warnings for x-*). Default false
   *     (standard draft 2020-12 URI).
   *   includeServerOnly: when true, fields marked serverOnly() are included.
   *     Default false (excluded from client-facing output).
   */
  public function toJsonSchema(array $options = []): array {
    $properties      = [];
    $required        = [];
    $unmapped        = [];
    $includeInternal = !empty($options['includeServerOnly']);

    foreach ($this->fields as $name => $field) {
      if ($field->isServerOnly() && !$includeInternal) {
        continue;
      }
      if (!$field->isMappable()) {
        $unmapped[] = $name;
        continue;
      }
      $properties[$name] = $field->toJsonSchema();
      if ($field->isRequired()) {
        $required[] = $name;
      }
    }

    // Merge external JSON Schema (GUI-defined fields) under builder fields.
    if ($this->mergedJsonSchema !== null) {
      $extProps    = (array) ($this->mergedJsonSchema['properties'] ?? []);
      $extRequired = $this->mergedJsonSchema['required'] ?? [];

      // Strip external properties whose builder-side counterpart is serverOnly.
      if (!$includeInternal) {
        $serverOnlyNames = [];
        foreach ($this->fields as $n => $f) {
          if ($f->isServerOnly()) {
            $serverOnlyNames[] = $n;
          }
        }
        foreach ($serverOnlyNames as $n) {
          unset($extProps[$n]);
          $extRequired = array_values(array_filter($extRequired, fn($r) => $r !== $n));
        }
      }

      // External properties go first; builder properties override on conflict.
      $properties = array_merge($extProps, $properties);
      $required   = array_values(array_unique(array_merge($extRequired, $required)));
    }

    $metaSchema = !empty($options['metaSchema']);
    $schema = [
      '$schema'    => $metaSchema ? self::META_SCHEMA_URI : 'https://json-schema.org/draft/2020-12/schema',
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
