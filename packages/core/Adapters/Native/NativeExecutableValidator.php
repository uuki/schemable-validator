<?php

namespace SchemableValidator\Adapters\Native;

use SchemableValidator\I18n\MessageDict;
use SchemableValidator\Validation\Coercion;
use SchemableValidator\Validation\ExecutableValidator;
use SchemableValidator\Validation\Formats;
use SchemableValidator\Validation\MessageResolver;

/**
 * Dependency-free, FE-faithful executable validator. Ports the per-field
 * semantics of packages/client/src/{constraint,validator}.ts to PHP so the
 * native BE path produces the SAME accept/reject decisions AND the same
 * canonical messages as the FE — with zero Respect/Opis involvement.
 *
 * Unlike OpisExecutableValidator (strict JSON Schema, no coercion), this honors
 * Coercion Contract v1 (form string "42" passes `type: integer`), so it can run
 * the same form-string "parity" fixtures as RespectAdapter and the FE.
 *
 * Errors accumulate (never short-circuit) in the canonical rule order
 *   type → minLength → maxLength → minimum → maximum → format → pattern → enum
 * matching constraintsFromSchema() in constraint.ts.
 *
 * x-transform / x-when are NOT applied here (same contract as the Respect/Opis
 * executables): the caller pre-transforms input and applies x-when afterwards.
 */
final class NativeExecutableValidator implements ExecutableValidator {
  /** Mirror of PATTERN_MAX_INPUT_LENGTH in constraint.ts. */
  private const PATTERN_MAX_INPUT_LENGTH = 500;

  /** @var array<string, array<string, mixed>> */
  private array $properties;

  /** @var string[] */
  private array $required;

  /** @var array<string, array<string, string>> field => (JSON Schema keyword => template) */
  private array $inlineMessages;

  private ?MessageDict $dict;

  /**
   * @param array<string, array<string, mixed>> $properties
   * @param string[] $required
   * @param array<string, array<string, string>> $inlineMessages
   */
  public function __construct(array $properties, array $required, array $inlineMessages = [], ?MessageDict $dict = null) {
    $this->properties     = $properties;
    $this->required       = $required;
    $this->inlineMessages = $inlineMessages;
    $this->dict           = $dict;
  }

  public function validate(array $data): array {
    $result = [];

    foreach ($this->properties as $field => $prop) {
      $raw      = $data[$field] ?? null;
      $required = in_array($field, $this->required, true);
      $isArray  = isset($prop['items']) || (($prop['type'] ?? null) === 'array');

      if ($isArray) {
        $values = is_array($raw) ? array_values($raw) : ($raw !== null ? [$raw] : []);
        [$valid, $errors] = $this->validateArray($values, $prop, $required, $field);
        $result[$field] = [
          'value'    => $values,
          'is_valid' => $valid,
          'errors'   => $valid ? null : implode("\n", $errors),
        ];
        continue;
      }

      $value = is_array($raw) ? implode(',', $raw) : (string) ($raw ?? '');
      [$valid, $errors] = $this->validateScalar($value, $prop, $required, $field);
      $result[$field] = [
        'value'    => $value,
        'is_valid' => $valid,
        'errors'   => $valid ? null : implode("\n", $errors),
      ];
    }

    return $result;
  }

  /**
   * @param array<string, mixed> $prop
   * @return array{0: bool, 1: string[]}
   */
  private function validateScalar(string $value, array $prop, bool $required, string $field): array {
    $isEmpty = $value === '';
    if ($required && $isEmpty) {
      return [false, [$this->message($field, 'required', 'required', [])]];
    }
    if ($isEmpty) {
      return [true, []]; // optional + empty → always valid
    }

    $errors = $this->runConstraints($value, $prop, $field);
    return [count($errors) === 0, $errors];
  }

  /**
   * @param array<int, mixed> $values
   * @param array<string, mixed> $prop
   * @return array{0: bool, 1: string[]}
   */
  private function validateArray(array $values, array $prop, bool $required, string $field): array {
    if ($required && count($values) === 0) {
      return [false, [$this->message($field, 'required', 'required', [])]];
    }
    if (count($values) === 0) {
      return [true, []];
    }

    $errors = [];
    $itemSchema = $prop['items'] ?? null;
    if (is_array($itemSchema)) {
      foreach ($values as $v) {
        foreach ($this->runConstraints((string) $v, $itemSchema, $field) as $e) {
          $errors[] = $e;
        }
      }
    }
    // Array-size messages mirror validator.ts (inlined there, not in the catalog).
    if (isset($prop['minItems']) && count($values) < $prop['minItems']) {
      $n = (int) $prop['minItems'];
      $errors[] = "must have at least {$n} item" . ($n !== 1 ? 's' : '');
    }
    if (isset($prop['maxItems']) && count($values) > $prop['maxItems']) {
      $n = (int) $prop['maxItems'];
      $errors[] = "must have no more than {$n} item" . ($n !== 1 ? 's' : '');
    }
    return [count($errors) === 0, $errors];
  }

  /**
   * Run the scalar constraint pipeline, accumulating messages in canonical order.
   *
   * @param array<string, mixed> $prop
   * @return string[]
   */
  private function runConstraints(string $value, array $prop, string $field): array {
    $errors = [];

    // type
    $type = $prop['type'] ?? null;
    $primary = is_array($type) ? self::firstNonNull($type) : $type;
    if ($primary === 'integer' && !Coercion::acceptsInteger($value)) {
      $errors[] = $this->message($field, 'type', 'integer', []);
    } elseif ($primary === 'number' && !Coercion::acceptsNumber($value)) {
      $errors[] = $this->message($field, 'type', 'number', []);
    } elseif ($primary === 'boolean' && !Coercion::acceptsBoolean($value)) {
      $errors[] = $this->message($field, 'type', 'boolean', []);
    }

    // minLength / maxLength (codepoint length, mirroring mb_strlen / [...value].length)
    $len = mb_strlen($value, 'UTF-8');
    if (isset($prop['minLength']) && $len < $prop['minLength']) {
      $min = (int) $prop['minLength'];
      $errors[] = $this->message($field, 'minLength', 'minLength', ['min' => $min, 'plural' => $min === 1 ? '' : 's']);
    }
    if (isset($prop['maxLength']) && $len > $prop['maxLength']) {
      $max = (int) $prop['maxLength'];
      $errors[] = $this->message($field, 'maxLength', 'maxLength', ['max' => $max, 'plural' => $max === 1 ? '' : 's']);
    }

    // minimum / maximum (Coercion Contract v1 numeric parse)
    $num = Coercion::toNumber($value);
    if (isset($prop['minimum']) && !(is_finite($num) && $num >= $prop['minimum'])) {
      $errors[] = $this->message($field, 'minimum', 'minimum', ['min' => $prop['minimum']]);
    }
    if (isset($prop['maximum']) && !(is_finite($num) && $num <= $prop['maximum'])) {
      $errors[] = $this->message($field, 'maximum', 'maximum', ['max' => $prop['maximum']]);
    }

    // format
    if (isset($prop['format'])) {
      $ok = Formats::matches($prop['format'], $value);
      if ($ok === false) {
        $errors[] = $this->message($field, 'format', $prop['format'], []);
      }
    }

    // pattern (skip very long inputs, mirroring constraint.ts ReDoS guard)
    if (isset($prop['pattern']) && mb_strlen($value, 'UTF-8') <= self::PATTERN_MAX_INPUT_LENGTH) {
      $matched = @preg_match('/' . $prop['pattern'] . '/u', $value);
      if ($matched === 0) {
        $errors[] = $this->message($field, 'pattern', 'pattern', []);
      }
      // preg error (false) → skip silently, like the FE catch
    }

    // enum
    if (isset($prop['enum']) && is_array($prop['enum']) && !in_array($value, $prop['enum'], true)) {
      $errors[] = $this->message($field, 'enum', 'enum', ['values' => implode(', ', $prop['enum'])]);
    }

    return $errors;
  }

  /**
   * Resolve a message via the shared MessageResolver chain.
   *
   * @param array<string, int|float|string> $vars
   */
  private function message(string $field, string $keyword, string $neutralRuleId, array $vars): string {
    return MessageResolver::resolve(
      $this->dict,
      $field,
      $neutralRuleId,
      $keyword,
      $vars,
      $this->inlineMessages,
      "must be a valid {$neutralRuleId}"
    );
  }

  /**
   * @param array<int, mixed> $types
   */
  private static function firstNonNull(array $types): ?string {
    foreach ($types as $t) {
      if ($t !== 'null') {
        return $t;
      }
    }
    return null;
  }
}
