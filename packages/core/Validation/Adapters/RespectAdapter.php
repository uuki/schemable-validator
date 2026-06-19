<?php

namespace SchemableValidator\Validation\Adapters;

use Respect\Validation\Validator as v;
use SchemableValidator\Rules\BooleanCoercion;
use SchemableValidator\Rules\DateFormat;
use SchemableValidator\Rules\DateTimeFormat;
use SchemableValidator\Rules\FileExtension;
use SchemableValidator\Rules\IntegerCoercion;
use SchemableValidator\Rules\NumberCoercion;
use SchemableValidator\Rules\TimeFormat;
use SchemableValidator\I18n\MessageDict;
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
  public function compile(array $jsonSchema, ?MessageDict $dict = null): ExecutableValidator {
    $required       = $jsonSchema['required'] ?? [];
    $schema         = [];
    $inlineMessages = [];

    foreach ($jsonSchema['properties'] ?? [] as $name => $prop) {
      $chain          = self::compileProperty($prop);
      $schema[$name]  = in_array($name, $required, true) ? $chain : v::optional($chain);
      if (!empty($prop['errorMessage'])) {
        $inlineMessages[$name] = $prop['errorMessage'];
      }
    }

    return new RespectExecutableValidator($schema, $dict, $inlineMessages);
  }

  /**
   * Compile a single JSON Schema property fragment (the value of
   * `properties.<name>` in a 2020-12 object schema) to a Respect validator.
   * Used by compile()'s per-property loop and by Validator::fromJsonSchema()
   * for raw JSON Schema input that bypasses SchemaBuilder.
   *
   * @param array<string, mixed> $prop
   */
  public static function compileProperty(array $prop): v {
    return self::compileDescriptors(self::jsonSchemaPropertyToDescriptors($prop));
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
        return v::create()->addRule((new DateFormat())->setName('date'));
      case 'dateTime':
        return v::create()->addRule((new DateTimeFormat())->setName('dateTime'));
      case 'time':
        return v::create()->addRule((new TimeFormat())->setName('time'));
      case 'uuid':
        return v::uuid();
      case 'ipv4':
        // setName so the violation id is 'ipv4' (not Respect's generic 'ip'),
        // letting describeViolations() map it to the distinct ipv4 message.
        return v::create()->addRule((new \Respect\Validation\Rules\Ip('*', FILTER_FLAG_IPV4))->setName('ipv4'));
      case 'ipv6':
        return v::create()->addRule((new \Respect\Validation\Rules\Ip('*', FILTER_FLAG_IPV6))->setName('ipv6'));
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
   * Describe each rule violation with its Respect ruleId, the JSON Schema keyword
   * it corresponds to, and the {var} substitution values — so the message
   * resolution path can honor inline errorMessage[keyword] templates with the
   * same interpolation the FE applies. Respect knowledge stays isolated here.
   *
   * @return array<int, array{ruleId: string, keyword: ?string, vars: array<string, int|float|string>, message: string}>
   */
  public static function describeViolations(\Respect\Validation\Exceptions\ValidationException $e): array {
    $children = [];
    if ($e instanceof \Respect\Validation\Exceptions\NestedValidationException) {
      foreach ($e->getIterator() as $child) {
        $children[] = $child;
      }
    }
    if (empty($children)) {
      $children[] = $e;
    }

    $violations = [];
    foreach ($children as $child) {
      $ruleId       = $child->getId();
      $violations[] = [
        'ruleId'        => $ruleId,
        'keyword'       => self::keywordFor($ruleId, $child),
        'neutralRuleId' => self::neutralRuleIdFor($ruleId, $child),
        'vars'          => self::varsFor($ruleId, $child),
        'message'       => $child->getMessage(),
      ];
    }
    return $violations;
  }

  /**
   * Map a Respect ruleId to the engine-neutral rule vocabulary used to key the
   * canonical DefaultMessages catalog and user MessageDict definitions. Unlike
   * keywordFor() (which collapses formats into `format` for inline errorMessage),
   * this keeps formats and types distinct so messages stay fine-grained.
   */
  private static function neutralRuleIdFor(string $ruleId, \Respect\Validation\Exceptions\ValidationException $e): ?string {
    if ($ruleId === 'length') {
      if ($e->getParam('minValue') !== null) {
        return 'minLength';
      }
      if ($e->getParam('maxValue') !== null) {
        return 'maxLength';
      }
      return null;
    }
    $map = [
      'stringType' => 'string',
      'intType'    => 'integer',
      'numericVal' => 'number',
      'boolType'   => 'boolean',
      'min'        => 'minimum',
      'max'        => 'maximum',
      'email'      => 'email',
      'url'        => 'uri',
      'date'       => 'date',
      'dateTime'   => 'date-time',
      'time'       => 'time',
      'uuid'       => 'uuid',
      'ipv4'       => 'ipv4',
      'ipv6'       => 'ipv6',
      'domain'     => 'hostname',
      'regex'      => 'pattern',
      'in'         => 'enum',
    ];
    return $map[$ruleId] ?? null;
  }

  /**
   * Map a Respect ruleId to the JSON Schema keyword used as the inline
   * errorMessage key. `length` is split into minLength/maxLength based on
   * which bound the exception carries. Returns null when no keyword maps.
   */
  private static function keywordFor(string $ruleId, \Respect\Validation\Exceptions\ValidationException $e): ?string {
    if ($ruleId === 'length') {
      if ($e->getParam('minValue') !== null) {
        return 'minLength';
      }
      if ($e->getParam('maxValue') !== null) {
        return 'maxLength';
      }
      return null;
    }
    $map = [
      'email'      => 'format',
      'url'        => 'format',
      'date'       => 'format',
      'dateTime'   => 'format',
      'time'       => 'format',
      'uuid'       => 'format',
      'ip'         => 'format',
      'domain'     => 'format',
      'regex'      => 'pattern',
      'in'         => 'enum',
      'stringType' => 'type',
      'intType'    => 'type',
      'numericVal' => 'type',
      'boolType'   => 'type',
      'min'        => 'minimum',
      'max'        => 'maximum',
    ];
    return $map[$ruleId] ?? null;
  }

  /**
   * Extract {var} substitution values from a violation, mirroring the constraint
   * value the FE substitutes ({min}/{max}).
   *
   * @return array<string, int|float|string>
   */
  private static function varsFor(string $ruleId, \Respect\Validation\Exceptions\ValidationException $e): array {
    if ($ruleId === 'length') {
      $min = $e->getParam('minValue');
      if ($min !== null) {
        return ['min' => $min, 'plural' => $min === 1 ? '' : 's'];
      }
      $max = $e->getParam('maxValue');
      if ($max !== null) {
        return ['max' => $max, 'plural' => $max === 1 ? '' : 's'];
      }
      return [];
    }
    if ($ruleId === 'min') {
      $v = $e->getParam('compareTo');
      return $v !== null ? ['min' => $v] : [];
    }
    if ($ruleId === 'max') {
      $v = $e->getParam('compareTo');
      return $v !== null ? ['max' => $v] : [];
    }
    if ($ruleId === 'in') {
      $haystack = $e->getParam('haystack');
      return is_array($haystack) ? ['values' => implode(', ', $haystack)] : [];
    }
    return [];
  }

  /**
   * Translate a JSON Schema property fragment (type/format/length/min/max/pattern/enum/
   * array items/minItems/maxItems) into {rule, args} descriptors, so the same
   * compileDescriptor() dispatch serves both the SV-builder path (compileField)
   * and raw-JSON-Schema input (compile).
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
      case 'array':
        $itemProp      = $prop['items'] ?? [];
        $descriptors[] = ['rule' => 'each', 'args' => [self::jsonSchemaPropertyToDescriptors($itemProp)]];
        break;
      default:
        $descriptors[] = ['rule' => 'string', 'args' => []];
        break;
    }

    if (isset($prop['minLength']) || isset($prop['maxLength'])) {
      $descriptors[] = ['rule' => 'length', 'args' => [$prop['minLength'] ?? null, $prop['maxLength'] ?? null]];
    }
    if (isset($prop['minItems'])) {
      $descriptors[] = ['rule' => 'length', 'args' => [$prop['minItems'], null]];
    }
    if (isset($prop['maxItems'])) {
      $descriptors[] = ['rule' => 'length', 'args' => [null, $prop['maxItems']]];
    }
    if (isset($prop['minimum'])) {
      $descriptors[] = ['rule' => 'min', 'args' => [$prop['minimum']]];
    }
    if (isset($prop['maximum'])) {
      $descriptors[] = ['rule' => 'max', 'args' => [$prop['maximum']]];
    }
    // Rule order is the BE/FE message-ordering contract: keep this sequence
    // (… minimum, maximum, format, pattern, enum) identical to
    // constraintsFromSchema() in packages/client/src/constraint.ts so that
    // multi-rule failures emit messages in the same order on both stacks.
    if (isset($prop['format'])) {
      switch ($prop['format']) {
        case 'email':
          $descriptors[] = ['rule' => 'email', 'args' => []];
          break;
        case 'uri':
          $descriptors[] = ['rule' => 'url', 'args' => []];
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
        case 'ipv4':
          $descriptors[] = ['rule' => 'ipv4', 'args' => []];
          break;
        case 'ipv6':
          $descriptors[] = ['rule' => 'ipv6', 'args' => []];
          break;
        case 'hostname':
          $descriptors[] = ['rule' => 'domain', 'args' => []];
          break;
      }
    }
    if (isset($prop['pattern'])) {
      $descriptors[] = ['rule' => 'pattern', 'args' => [$prop['pattern']]];
    }
    if (isset($prop['enum'])) {
      $descriptors[] = ['rule' => 'in', 'args' => [$prop['enum']]];
    }

    return $descriptors;
  }
}
