<?php

namespace SchemableValidator\Validation\Adapters;

use Respect\Validation\Validator as v;
use SchemableValidator\Rules\BooleanCoercion;
use SchemableValidator\Rules\FileExtension;
use SchemableValidator\Rules\IntegerCoercion;
use SchemableValidator\Rules\NumberCoercion;
use SchemableValidator\Schema\AbstractFieldSchema;
use SchemableValidator\Schema\MappableField;
use SchemableValidator\Schema\UnmappableField;
use SchemableValidator\Validation\BackendAdapter;
use SchemableValidator\Validation\ExecutableValidator;
use SchemableValidator\Validation\RespectExecutableValidator;

/**
 * Default BackendAdapter. Consolidates all Respect/Validation knowledge:
 * - JSON Schema -> ExecutableValidator (compile), the BackendAdapter contract.
 * - {rule, args} descriptor -> Respect validator dispatch (compileDescriptor),
 *   the execution half of the old RuleMapper::resolve() switch.
 * - AbstractFieldSchema -> Respect validator (compileField), used by
 *   SchemaBuilder::toValidator() for both MappableField descriptors and the
 *   UnmappableField escape hatches (FileSchema/RawRespectSchema).
 * - Respect exception -> ruleId/message extraction (extractRuleMessages),
 *   moved from Validator::extractRuleMessages().
 */
final class RespectAdapter implements BackendAdapter {
  public function compile(array $jsonSchema): ExecutableValidator {
    $required = $jsonSchema['required'] ?? [];
    $schema   = [];

    foreach ($jsonSchema['properties'] ?? [] as $name => $prop) {
      $chain          = self::compileDescriptors(self::jsonSchemaPropertyToDescriptors($prop));
      $schema[$name]  = in_array($name, $required, true) ? $chain : v::optional($chain);
    }

    return new RespectExecutableValidator($schema);
  }

  /** Compile a single field schema (Mappable or Unmappable) to a Respect validator. */
  public static function compileField(AbstractFieldSchema $field): v {
    if ($field instanceof UnmappableField) {
      return $field->toRespect();
    }
    if ($field instanceof MappableField) {
      return self::compileDescriptors($field->toDescriptors());
    }
    throw new \LogicException(get_class($field) . ' implements neither MappableField nor UnmappableField');
  }

  /** @param array<int, array{rule: string, args: array}> $descriptors */
  public static function compileDescriptors(array $descriptors): v {
    $chain = v::create();
    foreach ($descriptors as $descriptor) {
      $chain->addRule(self::compileDescriptor($descriptor['rule'], $descriptor['args']));
    }
    return $chain;
  }

  /** Dispatch a single {rule, args} descriptor to its Respect validator. */
  public static function compileDescriptor(string $rule, array $args): v {
    switch ($rule) {
      case 'string':
        return v::stringType();
      case 'integer':
        return v::create()->addRule((new IntegerCoercion())->setName('intType'));
      case 'number':
        return v::create()->addRule((new NumberCoercion())->setName('numericVal'));
      case 'boolean':
        return v::create()->addRule((new BooleanCoercion())->setName('boolType'));
      case 'email':
        return v::email();
      case 'url':
        return v::url();
      case 'length':
        return v::length($args[0] ?? null, $args[1] ?? null);
      case 'min':
        return v::min($args[0]);
      case 'max':
        return v::max($args[0]);
      case 'pattern':
        return v::regex('/' . $args[0] . '/u');
      case 'date':
        return v::date('Y-m-d');
      case 'dateTime':
        return v::dateTime();
      case 'time':
        return v::time('H:i:s');
      case 'uuid':
        return v::uuid();
      case 'ipv4':
        return v::ip('*', FILTER_FLAG_IPV4);
      case 'ipv6':
        return v::ip('*', FILTER_FLAG_IPV6);
      case 'slug':
        return v::slug();
      case 'domain':
        return v::domain();
      case 'in':
        return v::in($args[0]);
      case 'each':
        return v::each(self::compileDescriptors($args[0]));
      case 'fileExt':
        $fv = v::create();
        $fv->addRule(new FileExtension($args[0]));
        return $fv;
      default:
        throw new \InvalidArgumentException("RespectAdapter: no Respect mapping for rule '{$rule}'");
    }
  }

  /**
   * Isolates all Respect exception internals.
   * If Respect changes its exception hierarchy, update only here.
   * Tested against respect/validation 2.2.4.
   *
   * @return array<string, string> ruleId => defaultMessage
   */
  public static function extractRuleMessages(\Respect\Validation\Exceptions\ValidationException $e): array {
    $messages = [];
    if ($e instanceof \Respect\Validation\Exceptions\NestedValidationException) {
      foreach ($e->getIterator() as $child) {
        $messages[$child->getId()] = $child->getMessage();
      }
    }
    if (empty($messages)) {
      $messages[$e->getId()] = $e->getMessage();
    }
    return $messages;
  }

  /**
   * Translate a JSON Schema property fragment (type/format/length/min/max/pattern/enum)
   * into {rule, args} descriptors, so the same compileDescriptor() dispatch serves
   * both the SV-builder path (compileField) and raw-JSON-Schema input (compile).
   *
   * @param array<string, mixed> $prop
   * @return array<int, array{rule: string, args: array}>
   */
  private static function jsonSchemaPropertyToDescriptors(array $prop): array {
    $descriptors = [];

    switch ($prop['type'] ?? 'string') {
      case 'integer':
        $descriptors[] = ['rule' => 'integer', 'args' => []];
        break;
      case 'number':
        $descriptors[] = ['rule' => 'number', 'args' => []];
        break;
      case 'boolean':
        $descriptors[] = ['rule' => 'boolean', 'args' => []];
        break;
      default:
        $descriptors[] = ['rule' => 'string', 'args' => []];
        break;
    }

    if (isset($prop['minLength']) || isset($prop['maxLength'])) {
      $descriptors[] = ['rule' => 'length', 'args' => [$prop['minLength'] ?? null, $prop['maxLength'] ?? null]];
    }
    if (isset($prop['minimum'])) {
      $descriptors[] = ['rule' => 'min', 'args' => [$prop['minimum']]];
    }
    if (isset($prop['maximum'])) {
      $descriptors[] = ['rule' => 'max', 'args' => [$prop['maximum']]];
    }
    if (isset($prop['pattern'])) {
      $descriptors[] = ['rule' => 'pattern', 'args' => [$prop['pattern']]];
    }
    if (isset($prop['enum'])) {
      $descriptors[] = ['rule' => 'in', 'args' => [$prop['enum']]];
    }
    if (isset($prop['format'])) {
      switch ($prop['format']) {
        case 'email':
          $descriptors[] = ['rule' => 'email', 'args' => []];
          break;
        case 'date':
          $descriptors[] = ['rule' => 'date', 'args' => []];
          break;
        case 'date-time':
          $descriptors[] = ['rule' => 'dateTime', 'args' => []];
          break;
        case 'time':
          $descriptors[] = ['rule' => 'time', 'args' => []];
          break;
        case 'uuid':
          $descriptors[] = ['rule' => 'uuid', 'args' => []];
          break;
      }
    }

    return $descriptors;
  }
}
