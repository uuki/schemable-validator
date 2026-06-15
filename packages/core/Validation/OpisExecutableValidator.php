<?php

namespace SchemableValidator\Validation;

use Opis\JsonSchema\Validator as OpisValidator;

/**
 * Validates each property's value against its JSON Schema 2020-12
 * sub-schema using opis/json-schema directly (no Respect involved).
 *
 * Strict JSON Schema semantics, NOT Coercion Contract v1: a form string
 * like "42" against `{type: "integer"}` is INVALID here (opis has no
 * coercion), unlike RespectAdapter/FE. Intended for typed-JSON input.
 * See .claude/logs/design-direction.md (2026-06-15, W2).
 */
final class OpisExecutableValidator implements ExecutableValidator {
  /** @var array<string, array<string, mixed>> */
  private $properties;

  /** @var string[] */
  private $required;

  private OpisValidator $validator;

  /**
   * @param array<string, array<string, mixed>> $properties
   * @param string[] $required
   */
  public function __construct(array $properties, array $required) {
    $this->properties = $properties;
    $this->required   = $required;
    $this->validator  = new OpisValidator();
  }

  public function validate(array $data): array {
    $result = [];

    foreach ($this->properties as $field => $propSchema) {
      $value    = $data[$field] ?? null;
      $required = in_array($field, $this->required, true);

      if (in_array($value, [null, ''], true)) {
        $result[$field] = [
          'value'    => $value,
          'is_valid' => !$required,
          'errors'   => $required ? 'required' : null,
        ];
        continue;
      }

      $schemaObj = json_decode(json_encode($propSchema), false);
      $validation = $this->validator->validate($value, $schemaObj);

      $result[$field] = [
        'value'    => $value,
        'is_valid' => $validation->isValid(),
        'errors'   => $validation->isValid() ? null : $validation->error()->message(),
      ];
    }

    return $result;
  }
}
