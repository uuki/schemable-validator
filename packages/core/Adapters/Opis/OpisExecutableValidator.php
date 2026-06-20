<?php

namespace SchemableValidator\Adapters\Opis;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator as OpisValidator;
use SchemableValidator\I18n\MessageDict;
use SchemableValidator\Validation\ExecutableValidator;
use SchemableValidator\Validation\MessageResolver;

/**
 * Validates each property's value against its JSON Schema 2020-12
 * sub-schema using opis/json-schema directly (no Respect involved).
 *
 * Strict JSON Schema semantics, NOT Coercion Contract v1: a form string
 * like "42" against `{type: "integer"}` is INVALID here (opis has no
 * coercion), unlike RespectAdapter/FE. Intended for typed-JSON input.
 * See .claude/logs/design-direction.md (2026-06-15, W2).
 *
 * Messages are engine-neutral: opis's own error text is mapped to the shared
 * DefaultMessages catalog (same canonical strings as RespectAdapter/FE) keyed
 * by a neutral ruleId, with inline errorMessage[keyword] honored. opis text is
 * only a last-resort fallback for keywords with no neutral mapping. All opis
 * error internals stay isolated in this class.
 */
final class OpisExecutableValidator implements ExecutableValidator {
  /** @var array<string, array<string, mixed>> */
  private $properties;

  /** @var string[] */
  private $required;

  /** @var array<string, array<string, string>> field => (JSON Schema keyword => message template) */
  private array $inlineMessages;

  private ?MessageDict $dict;

  private OpisValidator $validator;

  /**
   * @param array<string, array<string, mixed>> $properties
   * @param string[] $required
   * @param array<string, array<string, string>> $inlineMessages
   */
  public function __construct(array $properties, array $required, array $inlineMessages = [], ?MessageDict $dict = null) {
    // opis/json-schema is an optional dependency (see composer.json "suggest").
    // The Respect and Native adapters cover the default and dependency-free
    // paths; fail with an actionable message if this adapter is used without it.
    if (!class_exists(OpisValidator::class)) {
      throw new \RuntimeException(
        'OpisExecutableValidator requires opis/json-schema, which is an optional '
        . 'dependency. Install it with: composer require opis/json-schema'
      );
    }

    $this->properties     = $properties;
    $this->required       = $required;
    $this->inlineMessages = $inlineMessages;
    $this->dict           = $dict;
    $this->validator      = new OpisValidator();
  }

  public function validate(array $data): array {
    $result = [];

    foreach ($this->properties as $field => $propSchema) {
      $value    = $data[$field] ?? null;
      $required = in_array($field, $this->required, true);

      if (in_array($value, [null, ''], true)) {
        $errors = null;
        if ($required) {
          $errors = MessageResolver::resolve(
            $this->dict,
            $field,
            'required',
            'required',
            [],
            $this->inlineMessages,
            'is required'
          );
        }
        $result[$field] = [
          'value'    => $value,
          'is_valid' => !$required,
          'errors'   => $errors,
        ];
        continue;
      }

      $schemaObj  = json_decode(json_encode($propSchema), false);
      $validation = $this->validator->validate($value, $schemaObj);

      $result[$field] = [
        'value'    => $value,
        'is_valid' => $validation->isValid(),
        'errors'   => $validation->isValid()
          ? null
          : $this->resolveMessage($field, $validation->error(), $propSchema),
      ];
    }

    return $result;
  }

  /**
   * Resolve an error message via the shared MessageResolver chain.
   *
   * @param array<string, mixed> $propSchema
   */
  private function resolveMessage(string $field, ValidationError $error, array $propSchema): string {
    $keyword = $error->keyword();
    [$neutral, $vars] = self::neutralViolation($error, $propSchema);

    return MessageResolver::resolve(
      $this->dict,
      $field,
      $neutral ?? ($keyword ?? ''),
      $keyword,
      $vars,
      $this->inlineMessages,
      $error->message()
    );
  }

  /**
   * Map an opis ValidationError to the engine-neutral rule vocabulary + {var}
   * substitution values, mirroring RespectAdapter::describeViolations(). enum
   * values are read from the property schema (opis omits them from args).
   *
   * @param array<string, mixed> $propSchema
   * @return array{0: ?string, 1: array<string, int|float|string>}
   */
  private static function neutralViolation(ValidationError $error, array $propSchema): array {
    $kw   = $error->keyword();
    $args = $error->args();

    switch ($kw) {
      case 'type':
        $expected = $args['expected'] ?? '';
        if (is_array($expected)) {
          $expected = '';
          foreach ($args['expected'] as $t) {
            if ($t !== 'null') { $expected = $t; break; }
          }
        }
        $map = ['string' => 'string', 'integer' => 'integer', 'number' => 'number', 'boolean' => 'boolean'];
        return [$map[$expected] ?? null, []];
      case 'minLength':
        $min = $args['min'] ?? null;
        return ['minLength', ['min' => $min, 'plural' => $min === 1 ? '' : 's']];
      case 'maxLength':
        $max = $args['max'] ?? null;
        return ['maxLength', ['max' => $max, 'plural' => $max === 1 ? '' : 's']];
      case 'minimum':
        return ['minimum', ['min' => $args['min'] ?? null]];
      case 'maximum':
        return ['maximum', ['max' => $args['max'] ?? null]];
      case 'pattern':
        return ['pattern', []];
      case 'enum':
        $values = $propSchema['enum'] ?? [];
        return ['enum', is_array($values) ? ['values' => implode(', ', $values)] : []];
      case 'format':
        // The neutral ruleId IS the format name (email/uri/date/.../ipv6).
        return [$args['format'] ?? null, []];
      default:
        return [null, []];
    }
  }
}
